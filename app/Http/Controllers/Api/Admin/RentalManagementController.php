<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use App\Notifications\PaymentConfirmedNotification;
use App\Notifications\RentalStatusUpdatedNotification;
use App\Events\NotificationEvent;
use Barryvdh\DomPDF\Facade\Pdf;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\RentalsExport;

class RentalManagementController extends Controller
{
    /**
     * List all rentals (admin)
     */
    public function index(Request $request)
    {
        $query = Rental::with(['user', 'items.product.primaryImage', 'payments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $rentals = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($rentals);
    }

    /**
     * Export all rentals to Excel
     */
    public function exportExcel(Request $request)
    {
        $query = Rental::with(['user', 'items.product', 'payments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $rentals = $query->orderBy('created_at', 'desc')->get();

        return Excel::download(new RentalsExport($rentals), 'Laporan-Transaksi-' . date('Y-m-d') . '.xlsx');
    }

    /**
     * Export all rentals to PDF
     */
    public function exportPdf(Request $request)
    {
        $query = Rental::with(['user', 'items.product', 'payments']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $rentals = $query->orderBy('created_at', 'desc')->get();

        $pdf = Pdf::loadView('exports.rentals', compact('rentals'));
        
        return $pdf->download('laporan-transaksi-'.date('Y-m-d').'.pdf');
    }

    /**
     * Confirm payment
     */
    public function confirmPayment(Request $request, int $paymentId)
    {
        $payment = Payment::findOrFail($paymentId);

        $request->validate([
            'status' => 'required|in:confirmed,rejected',
            'admin_notes' => 'nullable|string',
        ]);

        $payment->update([
            'status' => $request->status,
            'admin_notes' => $request->admin_notes,
            'confirmed_by' => $request->user()->id,
            'confirmed_at' => now(),
        ]);

        $rental = $payment->rental;

        if ($request->status === 'confirmed') {
            // Update rental DP/payment amounts
            $totalPaid = $rental->payments()->where('status', 'confirmed')->sum('amount');
            $remaining = max(0, $rental->total_amount - $totalPaid);
            
            $rentalUpdate = [
                'dp_amount' => $totalPaid,
                'remaining_amount' => $remaining,
            ];

            // Only update rental status if it's still in payment-related statuses
            if (in_array($rental->status, ['pending_payment', 'pending_confirmation'])) {
                $rentalUpdate['status'] = 'confirmed';
            }

            $rental->update($rentalUpdate);

            $paidLabel = $remaining <= 0 ? 'LUNAS' : 'DP sebesar Rp ' . number_format($totalPaid, 0, ',', '.');
            
            $rental->user->notify(new PaymentConfirmedNotification($rental, $payment));
            
            // Broadcast real-time notification
            event(new NotificationEvent(
                $rental->user_id,
                'Pembayaran Dikonfirmasi',
                "Pembayaran untuk pesanan {$rental->invoice_number} telah dikonfirmasi ({$paidLabel}).",
                'success'
            ));
        } elseif ($request->status === 'rejected') {
            // If rejected and no other confirmed payments exist, revert to pending_payment
            $hasOtherConfirmed = $rental->payments()->where('status', 'confirmed')->exists();
            if (!$hasOtherConfirmed && $rental->status === 'pending_confirmation') {
                $rental->update(['status' => 'pending_payment']);
            }
            
            event(new NotificationEvent(
                $rental->user_id,
                'Pembayaran Ditolak',
                "Pembayaran untuk pesanan {$rental->invoice_number} ditolak. Silakan upload ulang.",
                'error'
            ));
        }

        return response()->json([
            'message' => $request->status === 'confirmed'
                ? 'Pembayaran berhasil dikonfirmasi!'
                : 'Pembayaran ditolak.',
            'payment' => $payment->fresh(),
        ]);
    }

    /**
     * Update rental status (admin workflow)
     */
    public function updateStatus(Request $request, int $rentalId)
    {
        $rental = Rental::findOrFail($rentalId);

        $request->validate([
            'status' => 'required|in:pending_payment,pending,confirmed,ready_pickup,delivering,rented,returned,cancelled',
            'return_condition' => 'required_if:status,returned|nullable|in:perfect,minor_damage,major_damage,lost',
            'return_notes' => 'nullable|string',
        ]);

        $updateData = ['status' => $request->status];

        if ($request->status === 'returned') {
            $updateData['actual_return_date'] = now();
            $updateData['return_condition'] = $request->return_condition;
            $updateData['return_notes'] = $request->return_notes;

            // Calculate late fees
            if (Carbon::now()->greaterThan($rental->end_date)) {
                $overdueDays = $rental->end_date->startOfDay()->diffInDays(Carbon::now()->startOfDay());
                $lateFeePerDay = $rental->items->sum(function ($item) {
                    return $item->product->late_fee_per_day;
                });
                $updateData['late_fee_total'] = $lateFeePerDay * $overdueDays;
                $updateData['total_amount'] = $rental->total_amount + ($lateFeePerDay * $overdueDays);
                $updateData['remaining_amount'] = $updateData['total_amount'] - $rental->dp_amount;
            }

            // Release product units
            foreach ($rental->items as $item) {
                if ($item->productUnit) {
                    $newStatus = match ($request->return_condition) {
                        'lost', 'major_damage' => 'damaged',
                        default => 'available',
                    };
                    $item->productUnit->update(['status' => $newStatus]);
                }
            }
        }

        if ($request->status === 'cancelled') {
            foreach ($rental->items as $item) {
                if ($item->productUnit) {
                    $item->productUnit->update(['status' => 'available']);
                }
            }
        }

        $rental->update($updateData);

        $rental->user->notify(new RentalStatusUpdatedNotification($rental));

        // Format status name for notification
        $statusNames = [
            'ready_pickup' => 'Siap Diambil',
            'delivering' => 'Sedang Dikirim',
            'rented' => 'Sedang Disewa',
            'returned' => 'Telah Dikembalikan',
            'cancelled' => 'Dibatalkan',
        ];
        $statusName = $statusNames[$request->status] ?? $request->status;

        // Broadcast real-time notification
        event(new NotificationEvent(
            $rental->user_id,
            'Status Pesanan Diperbarui',
            "Pesanan {$rental->invoice_number} sekarang berstatus: {$statusName}.",
            'info'
        ));

        return response()->json([
            'message' => 'Status berhasil diperbarui',
            'rental' => $rental->fresh(['items.product']),
        ]);
    }

    /**
     * Blacklist a user
     */
    public function blacklistUser(Request $request, int $userId)
    {
        $request->validate([
            'is_blacklisted' => 'required|boolean',
            'reason' => 'required_if:is_blacklisted,true|nullable|string',
        ]);

        $user = User::findOrFail($userId);
        $user->update([
            'is_blacklisted' => $request->is_blacklisted,
            'blacklist_reason' => $request->reason,
        ]);

        return response()->json([
            'message' => $request->is_blacklisted ? 'User telah di-blacklist.' : 'User telah di-unblacklist.',
        ]);
    }

    /**
     * Generate PDF Invoice / Delivery Slip
     */
    public function generateInvoice(int $rentalId)
    {
        $rental = Rental::with(['user', 'items.product', 'items.productUnit'])->findOrFail($rentalId);
        
        $pdf = Pdf::loadView('pdf.invoice', ['rental' => $rental]);
        
        return $pdf->download("invoice-{$rental->invoice_number}.pdf");
    }
}
