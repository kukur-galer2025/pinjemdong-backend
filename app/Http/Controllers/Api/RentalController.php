<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Rental;
use App\Models\RentalItem;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\BankAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RentalController extends Controller
{
    /**
     * List user's rentals
     */
    public function index(Request $request)
    {
        $rentals = $request->user()->rentals()
            ->with(['items.product.primaryImage', 'payments'])
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($rentals);
    }

    /**
     * Show rental detail
     */
    public function show(Request $request, int $id)
    {
        $rental = $request->user()->rentals()
            ->with(['items.product.images', 'items.productUnit', 'payments'])
            ->findOrFail($id);



        return response()->json(['rental' => $rental]);
    }

    /**
     * Create a new rental (checkout)
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'rental_package_id' => 'nullable|exists:rental_packages,id',
            'items' => 'required_without:rental_package_id|array|min:1',
            'items.*.product_id' => 'required_without:rental_package_id|exists:products,id',
            'items.*.quantity' => 'required_without:rental_package_id|integer|min:1',
            'start_date' => 'required|date|after_or_equal:today',
            'pickup_time' => 'required|date_format:H:i',
            'end_date' => 'required|date|after_or_equal:start_date',
            'return_time' => 'required|date_format:H:i',
            'delivery_method' => 'required|in:pickup,delivery',
            'delivery_address' => 'required_if:delivery_method,delivery|nullable|string',
            'delivery_latitude' => 'required_if:delivery_method,delivery|nullable|numeric',
            'delivery_longitude' => 'required_if:delivery_method,delivery|nullable|numeric',
            'delivery_distance_km' => 'required_if:delivery_method,delivery|nullable|numeric|min:0',
            'notes' => 'nullable|string',
        ]);

        // Check user KYC verification
        $verification = $request->user()->verification;
        if (!$verification || $verification->status !== 'approved') {
            return response()->json([
                'message' => 'Anda harus menyelesaikan verifikasi identitas (KYC) sebelum bisa menyewa.',
            ], 422);
        }

        // Check blacklist
        if ($request->user()->is_blacklisted) {
            return response()->json([
                'message' => 'Akun Anda telah diblokir dan tidak bisa melakukan penyewaan.',
            ], 403);
        }

        return DB::transaction(function () use ($validated, $request) {
            $startDateTime = now()->parse($validated['start_date'] . ' ' . $validated['pickup_time']);
            $endDateTime = now()->parse($validated['end_date'] . ' ' . $validated['return_time']);
            
            if ($endDateTime->lessThanOrEqualTo($startDateTime)) {
                return response()->json([
                    'message' => 'Waktu pengembalian harus lebih lambat dari waktu pengambilan.',
                ], 422);
            }

            $diffInMinutes = $startDateTime->diffInMinutes($endDateTime);
            // 1 Hour Grace Period: First 25 hours count as 1 day.
            $billableMinutes = max(0, $diffInMinutes - 60);
            $totalDays = max(1, (int) ceil($billableMinutes / (24 * 60)));

            $subtotal = 0;
            $rentalItems = [];
            $maxDpPercentage = 0;
            $package = null;

            if (!empty($validated['rental_package_id'])) {
                $package = \App\Models\RentalPackage::with('items')->findOrFail($validated['rental_package_id']);
                $maxDpPercentage = $package->min_dp_percentage;
                $subtotal = $package->price_per_day * $totalDays;
                
                // Override items from package
                $validated['items'] = $package->items->map(function ($pkgItem) {
                    return [
                        'product_id' => $pkgItem->product_id,
                        'quantity' => $pkgItem->quantity,
                    ];
                })->toArray();
            }

            foreach ($validated['items'] as $item) {
                $product = Product::where('is_active', true)->findOrFail($item['product_id']);

                // Check availability
                $availableUnits = $product->units()
                    ->where('status', 'available')
                    ->take($item['quantity'])
                    ->get();

                if ($availableUnits->count() < $item['quantity']) {
                    return response()->json([
                        'message' => "Stok barang \"{$product->name}\" tidak mencukupi. Hanya tersedia {$availableUnits->count()} unit.",
                    ], 422);
                }

                if (!$package) {
                    $itemSubtotal = $product->price_per_day * $totalDays * $item['quantity'];
                    $subtotal += $itemSubtotal;

                    // Track highest DP percentage
                    if ($product->min_dp_percentage > $maxDpPercentage) {
                        $maxDpPercentage = $product->min_dp_percentage;
                    }
                }

                foreach ($availableUnits as $unit) {
                    $rentalItems[] = [
                        'product_id' => $product->id,
                        'product_unit_id' => $unit->id,
                        'quantity' => 1,
                        'price_per_day' => $package ? 0 : $product->price_per_day, // If package, individual item price is 0
                        'subtotal' => $package ? 0 : $product->price_per_day * $totalDays,
                    ];

                    // Mark unit as rented
                    $unit->update(['status' => 'rented']);
                }
            }

            // Calculate delivery cost based on distance (Rp 2,500 per km)
            $deliveryDistanceKm = 0;
            $deliveryCost = 0;
            if ($validated['delivery_method'] === 'delivery') {
                $deliveryDistanceKm = round($validated['delivery_distance_km'], 2);

                // Server-side validation: max 20km
                if ($deliveryDistanceKm > 20) {
                    return response()->json([
                        'message' => 'Jarak pengantaran melebihi 20 km dari lokasi toko. Silakan pilih metode Ambil Sendiri atau pilih lokasi yang lebih dekat.',
                    ], 422);
                }

                $deliveryCost = ceil($deliveryDistanceKm * 2500);
            }
            $totalAmount = $subtotal + $deliveryCost;
            $minDpAmount = ceil($totalAmount * $maxDpPercentage / 100);

            $rental = Rental::create([
                'invoice_number' => Rental::generateInvoiceNumber(),
                'user_id' => $request->user()->id,
                'start_date' => $validated['start_date'],
                'pickup_time' => $validated['pickup_time'],
                'end_date' => $validated['end_date'],
                'return_time' => $validated['return_time'],
                'total_days' => $totalDays,
                'subtotal' => $subtotal,
                'delivery_cost' => $deliveryCost,
                'total_amount' => $totalAmount,
                'dp_amount' => 0,
                'remaining_amount' => $totalAmount,
                'delivery_method' => $validated['delivery_method'],
                'delivery_address' => $validated['delivery_address'] ?? null,
                'delivery_latitude' => $validated['delivery_latitude'] ?? null,
                'delivery_longitude' => $validated['delivery_longitude'] ?? null,
                'delivery_distance_km' => $validated['delivery_method'] === 'delivery' ? $deliveryDistanceKm : null,
                'status' => 'pending_payment',
                'notes' => $validated['notes'] ?? null,
            ]);

            foreach ($rentalItems as $item) {
                $rental->items()->create($item);
            }

            // Get bank accounts for payment instructions
            $bankAccounts = BankAccount::where('is_active', true)->get();

            return response()->json([
                'message' => 'Pesanan berhasil dibuat! Silakan lakukan pembayaran.',
                'rental' => $rental->load('items.product'),
                'payment_info' => [
                    'total_amount' => $totalAmount,
                    'min_dp_amount' => $minDpAmount,
                    'min_dp_percentage' => $maxDpPercentage,
                    'bank_accounts' => $bankAccounts,
                ],
            ], 201);
        });
    }

    /**
     * Cancel a rental
     */
    public function cancel(Request $request, int $id)
    {
        $rental = $request->user()->rentals()->findOrFail($id);

        if (!in_array($rental->status, ['pending_payment', 'pending_confirmation'])) {
            return response()->json([
                'message' => 'Pesanan tidak dapat dibatalkan pada status ini.',
            ], 422);
        }

        // Release units
        foreach ($rental->items as $item) {
            if ($item->productUnit) {
                $item->productUnit->update(['status' => 'available']);
            }
        }

        $rental->update(['status' => 'cancelled']);

        return response()->json([
            'message' => 'Pesanan berhasil dibatalkan.',
        ]);
    }
}
