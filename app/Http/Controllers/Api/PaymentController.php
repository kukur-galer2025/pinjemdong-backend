<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Rental;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    /**
     * Upload payment proof (DP, full payment, or remaining balance)
     *
     * Payment Flow:
     * 1. New rental → status = pending_payment, dp_amount = 0, remaining_amount = total
     * 2. User uploads DP (min 30%) or full payment proof → status = pending_confirmation
     * 3. Admin confirms → dp_amount updated, remaining recalculated, status = confirmed
     * 4. If remaining > 0, user can upload remaining payment (status stays confirmed)
     * 5. Admin confirms remaining → remaining = 0, fully paid
     */
    public function uploadProof(Request $request, int $rentalId)
    {
        $request->validate([
            'type' => 'required|in:dp,full_payment,remaining',
            'amount' => 'required|numeric|min:1000',
            'proof_image' => 'required|image|max:5120', // 5MB max
        ]);

        $rental = $request->user()->rentals()->findOrFail($rentalId);

        // Only allow payments on appropriate statuses
        $allowedStatuses = ['pending_payment', 'pending_confirmation', 'confirmed', 'rented'];
        if (!in_array($rental->status, $allowedStatuses)) {
            return response()->json([
                'message' => 'Tidak dapat mengunggah bukti pembayaran pada status ini.',
            ], 422);
        }

        // Check for any pending (unconfirmed) payment to prevent duplicate uploads
        $hasPending = $rental->payments()->where('status', 'pending')->exists();
        if ($hasPending) {
            return response()->json([
                'message' => 'Anda memiliki pembayaran yang masih menunggu konfirmasi admin. Harap tunggu hingga dikonfirmasi.',
            ], 422);
        }

        // Calculate how much has been confirmed so far
        $totalConfirmed = (float) $rental->payments()->where('status', 'confirmed')->sum('amount');
        $totalAmount = (float) $rental->total_amount;
        $actualRemaining = round($totalAmount - $totalConfirmed, 2);
        $minDp = ceil($totalAmount * 0.3); // 30% minimum DP
        $amount = (float) $request->amount;

        // Validation based on payment type
        if ($request->type === 'dp') {
            // First payment: must be at least 30% of total
            if ($totalConfirmed > 0) {
                return response()->json([
                    'message' => 'Anda sudah pernah membayar DP. Gunakan tipe "Pelunasan Sisa" untuk melunasi.',
                ], 422);
            }
            if ($amount < $minDp) {
                return response()->json([
                    'message' => "Minimal DP adalah Rp " . number_format($minDp, 0, ',', '.') . " (30% dari total Rp " . number_format($totalAmount, 0, ',', '.') . ").",
                ], 422);
            }
            if ($amount > $totalAmount) {
                return response()->json([
                    'message' => "Jumlah DP tidak boleh melebihi total tagihan Rp " . number_format($totalAmount, 0, ',', '.') . ".",
                ], 422);
            }
        } elseif ($request->type === 'full_payment') {
            // Full payment: must equal total amount
            if ($totalConfirmed > 0) {
                return response()->json([
                    'message' => 'Anda sudah pernah membayar. Gunakan tipe "Pelunasan Sisa" untuk melunasi.',
                ], 422);
            }
            if ($amount != $totalAmount) {
                return response()->json([
                    'message' => "Pembayaran lunas harus sebesar Rp " . number_format($totalAmount, 0, ',', '.') . ".",
                ], 422);
            }
        } elseif ($request->type === 'remaining') {
            // Remaining: must equal actual remaining
            if ($totalConfirmed <= 0) {
                return response()->json([
                    'message' => 'Belum ada pembayaran DP. Silakan bayar DP terlebih dahulu.',
                ], 422);
            }
            if ($actualRemaining <= 0) {
                return response()->json([
                    'message' => 'Tagihan Anda sudah lunas. Tidak perlu membayar lagi.',
                ], 422);
            }
            if ($amount != $actualRemaining) {
                return response()->json([
                    'message' => "Pelunasan sisa harus sebesar Rp " . number_format($actualRemaining, 0, ',', '.') . ".",
                ], 422);
            }
        }

        $path = $request->file('proof_image')->store('payment-proofs', 'public');

        $payment = Payment::create([
            'rental_id' => $rental->id,
            'type' => $request->type,
            'amount' => $amount,
            'proof_image' => $path,
            'status' => 'pending',
        ]);

        // Update rental status to pending_confirmation
        if ($rental->status === 'pending_payment') {
            $rental->update(['status' => 'pending_confirmation']);
        }

        return response()->json([
            'message' => 'Bukti pembayaran berhasil diunggah! Menunggu konfirmasi admin.',
            'payment' => $payment,
            'summary' => [
                'total_amount' => $totalAmount,
                'total_confirmed' => $totalConfirmed,
                'this_payment' => $amount,
                'remaining_after' => round($actualRemaining - $amount, 2),
            ],
        ], 201);
    }

    /**
     * Get payments for a rental
     */
    public function getByRental(Request $request, int $rentalId)
    {
        $rental = $request->user()->rentals()->findOrFail($rentalId);

        $payments = $rental->payments()->orderBy('created_at', 'desc')->get();

        // Also return computed summary
        $totalConfirmed = (float) $payments->where('status', 'confirmed')->sum('amount');
        $totalPending = (float) $payments->where('status', 'pending')->sum('amount');
        $totalAmount = (float) $rental->total_amount;

        return response()->json([
            'payments' => $payments,
            'summary' => [
                'total_amount' => $totalAmount,
                'total_confirmed' => $totalConfirmed,
                'total_pending' => $totalPending,
                'remaining' => round($totalAmount - $totalConfirmed, 2),
                'min_dp' => ceil($totalAmount * 0.3),
                'is_fully_paid' => $totalConfirmed >= $totalAmount,
                'has_pending_payment' => $totalPending > 0,
            ],
        ]);
    }
}
