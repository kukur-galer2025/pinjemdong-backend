<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Rental;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Category;
use App\Models\UserVerification;
use Carbon\Carbon;
use App\Notifications\KycStatusUpdatedNotification;
use App\Events\NotificationEvent;

class DashboardController extends Controller
{
    /**
     * Admin dashboard stats overview
     */
    public function stats()
    {
        $totalUsers = User::where('role', 'customer')->count();
        $totalProducts = Product::count();
        $totalCategories = Category::count();
        $totalRentals = Rental::count();
        $activeRentals = Rental::whereIn('status', ['confirmed', 'rented', 'ready_pickup', 'delivering'])->count();
        $pendingPayments = Payment::where('status', 'pending')->count();
        $pendingKyc = UserVerification::where('status', 'pending')->count();

        $revenueThisMonth = Payment::where('status', 'confirmed')
            ->whereMonth('confirmed_at', now()->month)
            ->whereYear('confirmed_at', now()->year)
            ->sum('amount');

        $revenueTotal = Payment::where('status', 'confirmed')->sum('amount');

        // Generate chart data for the last 30 days
        $startDate = Carbon::now()->subDays(29)->startOfDay();
        $endDate = Carbon::now()->endOfDay();

        $dailyRevenues = Payment::where('status', 'confirmed')
            ->whereBetween('confirmed_at', [$startDate, $endDate])
            ->selectRaw('DATE(confirmed_at) as date, SUM(amount) as total')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $chartData = [];
        for ($i = 0; $i < 30; $i++) {
            $dateStr = Carbon::now()->subDays(29 - $i)->format('Y-m-d');
            $chartData[] = [
                'name' => Carbon::parse($dateStr)->format('d M'),
                'revenue' => isset($dailyRevenues[$dateStr]) ? (float) $dailyRevenues[$dateStr]->total : 0,
            ];
        }

        $recentRentals = Rental::with(['user', 'items.product'])
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        // 1. Top 5 Products
        $topProducts = \Illuminate\Support\Facades\DB::table('rental_items')
            ->join('products', 'rental_items.product_id', '=', 'products.id')
            ->join('rentals', 'rental_items.rental_id', '=', 'rentals.id')
            ->whereIn('rentals.status', ['confirmed', 'rented', 'returned', 'completed'])
            ->select('products.name', \Illuminate\Support\Facades\DB::raw('SUM(rental_items.quantity) as total_rented'))
            ->groupBy('products.id', 'products.name')
            ->orderByDesc('total_rented')
            ->limit(5)
            ->get();

        // 2. Category Distribution
        $categoryDistribution = \Illuminate\Support\Facades\DB::table('rental_items')
            ->join('products', 'rental_items.product_id', '=', 'products.id')
            ->join('categories', 'products.category_id', '=', 'categories.id')
            ->join('rentals', 'rental_items.rental_id', '=', 'rentals.id')
            ->whereIn('rentals.status', ['confirmed', 'rented', 'returned', 'completed'])
            ->select('categories.name', \Illuminate\Support\Facades\DB::raw('SUM(rental_items.quantity) as total_rented'))
            ->groupBy('categories.id', 'categories.name')
            ->orderByDesc('total_rented')
            ->get();

        $recentPayments = Payment::with(['rental.user'])
            ->where('status', 'pending')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return response()->json([
            'stats' => [
                'total_users' => $totalUsers,
                'total_products' => $totalProducts,
                'total_categories' => $totalCategories,
                'total_rentals' => $totalRentals,
                'active_rentals' => $activeRentals,
                'pending_payments' => $pendingPayments,
                'pending_kyc' => $pendingKyc,
                'revenue_this_month' => $revenueThisMonth,
                'revenue_total' => $revenueTotal,
            ],
            'chart_data' => $chartData,
            'top_products' => $topProducts,
            'category_distribution' => $categoryDistribution,
            'recent_rentals' => $recentRentals,
            'recent_payments' => $recentPayments,
        ]);
    }

    /**
     * List all KYC verifications
     */
    public function kycList()
    {
        $verifications = UserVerification::with('user')
            ->orderByRaw("FIELD(status, 'pending', 'approved', 'rejected')")
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($verifications);
    }

    /**
     * Update KYC status
     */
    public function kycUpdate(int $id, \Illuminate\Http\Request $request)
    {
        $request->validate([
            'status' => 'required|in:approved,rejected',
            'rejection_reason' => 'required_if:status,rejected|nullable|string',
        ]);

        $verification = UserVerification::findOrFail($id);
        $verification->update([
            'status' => $request->status,
            'rejection_reason' => $request->rejection_reason,
            'verified_by' => $request->user()->id,
            'verified_at' => now(),
        ]);

        $statusStr = $request->status === 'approved' ? 'verified' : 'rejected';
        $verification->user->notify(new KycStatusUpdatedNotification($statusStr, $request->rejection_reason));

        // Broadcast real-time notification
        event(new NotificationEvent(
            $verification->user_id,
            'Status KYC Diperbarui',
            $request->status === 'approved' 
                ? 'Selamat! Identitas KYC Anda telah diverifikasi.' 
                : 'Maaf, verifikasi KYC Anda ditolak: ' . $request->rejection_reason,
            $request->status === 'approved' ? 'success' : 'error'
        ));

        return response()->json([
            'message' => $request->status === 'approved'
                ? 'KYC berhasil diverifikasi!'
                : 'KYC ditolak.',
            'verification' => $verification->fresh()->load('user'),
        ]);
    }

    /**
     * Get all payments (admin)
     */
    public function paymentList(\Illuminate\Http\Request $request)
    {
        $query = Payment::with(['rental.user', 'rental.items.product']);

        if ($request->has('status')) {
            $query->where('status', $request->status);
        }

        $payments = $query->orderBy('created_at', 'desc')->paginate(10);
        return response()->json($payments);
    }

    /**
     * Get all users (admin)
     */
    public function userList(\Illuminate\Http\Request $request)
    {
        $query = User::with('verification')->where('role', 'customer');

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        $users = $query->orderBy('created_at', 'desc')->paginate(10);
        return response()->json($users);
    }
}
