<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function index(Request $request)
    {
        $wishlist = $request->user()->wishlist()
            ->with(['primaryImage', 'category'])
            ->withCount('reviews')
            ->withAvg('reviews', 'rating')
            ->get();

        return response()->json(['wishlist' => $wishlist]);
    }

    public function toggle(Request $request, int $productId)
    {
        $user = $request->user();
        $exists = $user->wishlist()->where('product_id', $productId)->exists();

        if ($exists) {
            $user->wishlist()->detach($productId);
            return response()->json(['message' => 'Dihapus dari wishlist.', 'wishlisted' => false]);
        } else {
            $user->wishlist()->attach($productId);
            return response()->json(['message' => 'Ditambahkan ke wishlist!', 'wishlisted' => true]);
        }
    }
}
