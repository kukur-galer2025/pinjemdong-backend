<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Review;
use Illuminate\Http\Request;

class ReviewManagementController extends Controller
{
    /**
     * Get all reviews with product and user details
     */
    public function index(Request $request)
    {
        $query = Review::with(['user', 'product']);

        if ($request->has('product_id')) {
            $query->where('product_id', $request->product_id);
        }

        $reviews = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($reviews);
    }

    /**
     * Reply to a review
     */
    public function reply(Request $request, $id)
    {
        $review = Review::findOrFail($id);

        $validated = $request->validate([
            'admin_reply' => 'required|string|max:1000',
        ]);

        $review->update([
            'admin_reply' => $validated['admin_reply']
        ]);

        return response()->json([
            'message' => 'Balasan berhasil dikirim!',
            'review' => $review->fresh()->load(['user', 'product'])
        ]);
    }
}
