<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\Category;
use App\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class ProductManagementController extends Controller
{
    /**
     * List all products for admin with full details
     */
    public function index(Request $request)
    {
        $query = Product::with(['category', 'images', 'units']);

        if ($request->has('category_id')) {
            $query->where('category_id', $request->category_id);
        }

        if ($request->has('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('brand', 'like', "%{$search}%");
            });
        }

        $products = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($products);
    }

    /**
     * Get a single product for admin
     */
    public function show($id)
    {
        $product = Product::with(['category', 'images', 'units'])->findOrFail($id);
        return response()->json(['product' => $product]);
    }

    /**
     * Create a new product
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'category_id' => 'required|exists:categories,id',
            'name' => 'required|string|max:255',
            'brand' => 'nullable|string|max:255',
            'description' => 'required|string',
            'terms_conditions' => 'nullable|string',
            'price_per_day' => 'required|numeric|min:0',
            'min_dp_percentage' => 'required|integer|min:10|max:100',
            'late_fee_per_day' => 'required|numeric|min:0',
            'total_units' => 'required|integer|min:1',
            'is_featured' => 'sometimes|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']) . '-' . time();
        $validated['is_active'] = $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true;

        // Extract images from validated before creating product
        $imageFiles = $request->file('images');
        unset($validated['images']);

        $product = Product::create($validated);

        // Handle Image Uploads
        if ($imageFiles && is_array($imageFiles)) {
            foreach ($imageFiles as $index => $file) {
                $path = $file->store('products', 'public');
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => '/storage/' . $path,
                    'is_primary' => $index === 0, // First image is primary
                    'sort_order' => $index + 1
                ]);
            }
        }

        for ($i = 1; $i <= $validated['total_units']; $i++) {
            $product->units()->create([
                'serial_number' => strtoupper(substr($product->slug, 0, 8)) . '-' . str_pad($i, 3, '0', STR_PAD_LEFT),
                'condition_notes' => 'Baik',
                'status' => 'available',
            ]);
        }

        return response()->json([
            'message' => 'Produk berhasil ditambahkan!',
            'product' => $product->load(['category', 'units']),
        ], 201);
    }

    /**
     * Update product
     */
    public function update(Request $request, int $id)
    {
        $product = Product::findOrFail($id);

        $validated = $request->validate([
            'category_id' => 'sometimes|exists:categories,id',
            'name' => 'sometimes|string|max:255',
            'brand' => 'nullable|string|max:255',
            'description' => 'sometimes|string',
            'terms_conditions' => 'nullable|string',
            'price_per_day' => 'sometimes|numeric|min:0',
            'min_dp_percentage' => 'sometimes|integer|min:10|max:100',
            'late_fee_per_day' => 'sometimes|numeric|min:0',
            'is_active' => 'sometimes|boolean',
            'is_featured' => 'sometimes|boolean',
            'images' => 'nullable|array',
            'images.*' => 'image|mimes:jpeg,png,jpg,webp|max:2048',
        ]);

        // Handle string boolean from FormData
        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }
        if (isset($validated['is_featured'])) {
            $validated['is_featured'] = filter_var($validated['is_featured'], FILTER_VALIDATE_BOOLEAN);
        }

        $imageFiles = $request->file('images');
        unset($validated['images']);

        $product->update($validated);

        if ($imageFiles && is_array($imageFiles)) {
            // Calculate current max sort order
            $maxSortOrder = $product->images()->max('sort_order') ?? 0;
            $hasPrimary = $product->images()->where('is_primary', true)->exists();

            foreach ($imageFiles as $index => $file) {
                $path = $file->store('products', 'public');
                $isPrimary = (!$hasPrimary && $index === 0);
                
                ProductImage::create([
                    'product_id' => $product->id,
                    'image_path' => '/storage/' . $path,
                    'is_primary' => $isPrimary,
                    'sort_order' => $maxSortOrder + $index + 1
                ]);
            }
        }

        return response()->json([
            'message' => 'Produk berhasil diperbarui!',
            'product' => $product->fresh()->load(['category', 'units']),
        ]);
    }

    /**
     * Delete product (soft / hard)
     */
    public function destroy(int $id)
    {
        $product = Product::findOrFail($id);

        // Check if product has active rentals
        $hasActiveRentals = $product->rentalItems()
            ->whereHas('rental', function ($q) {
                $q->whereIn('status', ['pending', 'confirmed', 'rented']);
            })
            ->exists();

        if ($hasActiveRentals) {
            return response()->json([
                'message' => 'Produk tidak bisa dihapus karena masih ada penyewaan aktif.',
            ], 422);
        }

        $product->update(['is_active' => false]);

        return response()->json([
            'message' => 'Produk berhasil dinonaktifkan.',
        ]);
    }

    /**
     * Delete a specific product image
     */
    public function destroyImage(int $productId, int $imageId)
    {
        $product = Product::findOrFail($productId);
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        // Delete file
        $path = str_replace('/storage/', '', $image->image_path);
        Storage::disk('public')->delete($path);

        $wasPrimary = $image->is_primary;
        $image->delete();

        // If it was primary, make the first remaining image primary
        if ($wasPrimary) {
            $nextImage = ProductImage::where('product_id', $productId)->orderBy('sort_order')->first();
            if ($nextImage) {
                $nextImage->update(['is_primary' => true]);
            }
        }

        return response()->json(['message' => 'Gambar berhasil dihapus']);
    }

    /**
     * Set a specific image as primary
     */
    public function setPrimaryImage(int $productId, int $imageId)
    {
        $product = Product::findOrFail($productId);
        $image = ProductImage::where('product_id', $productId)->findOrFail($imageId);

        // Remove primary flag from all other images
        ProductImage::where('product_id', $productId)->update(['is_primary' => false]);

        // Set the new primary image
        $image->update(['is_primary' => true]);

        return response()->json([
            'message' => 'Gambar berhasil dijadikan foto utama',
            'product' => $product->fresh()->load(['category', 'units', 'images'])
        ]);
    }
}
