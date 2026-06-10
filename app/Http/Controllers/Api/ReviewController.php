<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'product_id' => 'required|exists:products,id',
            'rental_id' => 'required|exists:rentals,id',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'nullable|string|max:1000',
            'photo' => 'nullable|image|max:2048', // max 2MB
        ]);

        // Verify user actually rented this product
        $rental = $request->user()->rentals()
            ->where('id', $validated['rental_id'])
            ->where('status', 'returned')
            ->whereHas('items', fn($q) => $q->where('product_id', $validated['product_id']))
            ->firstOrFail();

        $photoUrl = null;
        if ($request->hasFile('photo')) {
            $photoUrl = $request->file('photo')->store('reviews', 'public');
        }

        $review = Review::updateOrCreate(
            [
                'user_id' => $request->user()->id,
                'rental_id' => $validated['rental_id'],
                'product_id' => $validated['product_id'],
            ],
            [
                'rating' => $validated['rating'],
                'comment' => $validated['comment'],
                'photo_url' => $photoUrl,
            ]
        );

        return response()->json([
            'message' => 'Ulasan berhasil dikirim!',
            'review' => $review->load('user'),
        ], 201);
    }

    /**
     * Get reviews for a product
     */
    public function getByProduct(string $slug)
    {
        $product = \App\Models\Product::where('slug', $slug)->firstOrFail();

        $reviews = Review::with('user')
            ->where('product_id', $product->id)
            ->orderBy('created_at', 'desc')
            ->paginate(10);

        return response()->json($reviews);
    }
}
