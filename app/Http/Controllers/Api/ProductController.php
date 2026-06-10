<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use Illuminate\Http\Request;

class ProductController extends Controller
{
    /**
     * List products with filters
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'primaryImage'])
            ->where('is_active', true)
            ->withCount(['reviews', 'availableUnits'])
            ->withAvg('reviews', 'rating');

        // Filter by category
        if ($request->has('category')) {
            $query->whereHas('category', function ($q) use ($request) {
                $q->where('slug', $request->category);
            });
        }

        // Search by name or description
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('description', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        // Filter by price range
        if ($request->has('min_price')) {
            $query->where('price_per_day', '>=', $request->min_price);
        }
        if ($request->has('max_price')) {
            $query->where('price_per_day', '<=', $request->max_price);
        }

        // Filter featured only
        if ($request->boolean('featured')) {
            $query->where('is_featured', true);
        }

        // Filter available only
        if ($request->boolean('available_only')) {
            $query->whereHas('availableUnits');
        }

        // Filter by brand
        if ($request->has('brand') && $request->brand) {
            $query->where('brand', $request->brand);
        }

        // Sort options
        $sortBy = $request->get('sort', 'latest');
        match ($sortBy) {
            'price_asc' => $query->orderBy('price_per_day', 'asc'),
            'price_desc' => $query->orderBy('price_per_day', 'desc'),
            'popular' => $query->orderBy('reviews_count', 'desc'),
            'rating' => $query->orderBy('reviews_avg_rating', 'desc'),
            default => $query->orderBy('created_at', 'desc'),
        };

        $products = $query->paginate($request->get('per_page', 12));

        return response()->json($products);
    }

    /**
     * Show a single product by slug
     */
    public function show(string $slug)
    {
        $product = Product::with([
            'category',
            'images',
            'units' => fn($q) => $q->where('status', 'available'),
            'reviews.user',
        ])
            ->where('slug', $slug)
            ->where('is_active', true)
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->firstOrFail();

        $product->available_units_count = $product->units->count();

        // Get booked dates for this product
        $bookedDates = $product->units()
            ->join('rental_items', 'product_units.id', '=', 'rental_items.product_unit_id')
            ->join('rentals', 'rental_items.rental_id', '=', 'rentals.id')
            ->whereIn('rentals.status', ['confirmed', 'ready_pickup', 'delivering', 'rented'])
            ->select('rentals.start_date', 'rentals.end_date')
            ->get();

        return response()->json([
            'product' => $product,
            'booked_dates' => $bookedDates,
        ]);
    }

    /**
     * Check availability for specific dates
     */
    public function checkAvailability(Request $request, string $slug)
    {
        $request->validate([
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after:start_date',
        ]);

        $product = Product::where('slug', $slug)->firstOrFail();

        // Count units that are NOT booked for these dates
        $bookedUnitIds = $product->units()
            ->join('rental_items', 'product_units.id', '=', 'rental_items.product_unit_id')
            ->join('rentals', 'rental_items.rental_id', '=', 'rentals.id')
            ->whereIn('rentals.status', ['confirmed', 'ready_pickup', 'delivering', 'rented'])
            ->where(function ($q) use ($request) {
                $q->where(function ($q2) use ($request) {
                    $q2->where('rentals.start_date', '<=', $request->end_date)
                        ->where('rentals.end_date', '>=', $request->start_date);
                });
            })
            ->pluck('product_units.id');

        $availableUnits = $product->units()
            ->where('status', 'available')
            ->whereNotIn('id', $bookedUnitIds)
            ->count();

        $totalDays = now()->parse($request->start_date)->diffInDays(now()->parse($request->end_date));
        $subtotal = $product->price_per_day * $totalDays;
        $minDp = ceil($subtotal * $product->min_dp_percentage / 100);

        return response()->json([
            'available' => $availableUnits > 0,
            'available_units' => $availableUnits,
            'total_days' => $totalDays,
            'price_per_day' => $product->price_per_day,
            'subtotal' => $subtotal,
            'min_dp_percentage' => $product->min_dp_percentage,
            'min_dp_amount' => $minDp,
        ]);
    }
}
