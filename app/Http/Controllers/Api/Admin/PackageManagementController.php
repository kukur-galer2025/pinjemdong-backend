<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\RentalPackage;
use App\Models\RentalPackageItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;

class PackageManagementController extends Controller
{
    /**
     * List all rental packages
     */
    public function index(Request $request)
    {
        $query = RentalPackage::with(['products']);

        if ($request->has('search')) {
            $search = $request->search;
            $query->where('name', 'like', "%{$search}%");
        }

        $packages = $query->orderBy('created_at', 'desc')->paginate(10);

        return response()->json($packages);
    }

    /**
     * Get a single rental package for admin
     */
    public function show($id)
    {
        $package = RentalPackage::with(['products'])->findOrFail($id);
        return response()->json(['package' => $package]);
    }

    /**
     * Create a new rental package
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'required|string',
            'price_per_day' => 'required|numeric|min:0',
            'original_price_per_day' => 'required|numeric|min:0',
            'min_dp_percentage' => 'required|integer|min:10|max:100',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|exists:products,id',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        $validated['slug'] = \Illuminate\Support\Str::slug($validated['name']) . '-' . time();
        $validated['is_active'] = $request->has('is_active') ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN) : true;

        $imageFile = $request->file('image');
        unset($validated['image']);
        $items = $validated['items'];
        unset($validated['items']);

        DB::beginTransaction();
        try {
            if ($imageFile) {
                $path = $imageFile->store('packages', 'public');
                $validated['image'] = '/storage/' . $path;
            }

            $package = RentalPackage::create($validated);

            foreach ($items as $item) {
                RentalPackageItem::create([
                    'rental_package_id' => $package->id,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                ]);
            }

            DB::commit();

            return response()->json([
                'message' => 'Paket Sewa berhasil ditambahkan!',
                'package' => $package->load('products'),
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal menyimpan paket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Update an existing package
     */
    public function update(Request $request, int $id)
    {
        $package = RentalPackage::findOrFail($id);

        $validated = $request->validate([
            'name' => 'sometimes|string|max:255',
            'description' => 'sometimes|string',
            'price_per_day' => 'sometimes|numeric|min:0',
            'original_price_per_day' => 'sometimes|numeric|min:0',
            'min_dp_percentage' => 'sometimes|integer|min:10|max:100',
            'is_active' => 'sometimes|boolean',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,webp|max:2048',
            'items' => 'sometimes|array',
            'items.*.product_id' => 'required_with:items|exists:products,id',
            'items.*.quantity' => 'required_with:items|integer|min:1',
        ]);

        if (isset($validated['is_active'])) {
            $validated['is_active'] = filter_var($validated['is_active'], FILTER_VALIDATE_BOOLEAN);
        }

        $imageFile = $request->file('image');
        unset($validated['image']);
        
        $items = $validated['items'] ?? null;
        unset($validated['items']);

        DB::beginTransaction();
        try {
            if ($imageFile) {
                // Delete old file
                if ($package->image && str_starts_with($package->image, '/storage/')) {
                    $oldPath = str_replace('/storage/', '', $package->image);
                    Storage::disk('public')->delete($oldPath);
                }
                $path = $imageFile->store('packages', 'public');
                $validated['image'] = '/storage/' . $path;
            }

            $package->update($validated);

            if ($items !== null) {
                // Remove old items
                RentalPackageItem::where('rental_package_id', $package->id)->delete();
                
                // Add new items
                foreach ($items as $item) {
                    RentalPackageItem::create([
                        'rental_package_id' => $package->id,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['quantity'],
                    ]);
                }
            }

            DB::commit();

            return response()->json([
                'message' => 'Paket Sewa berhasil diperbarui!',
                'package' => $package->fresh()->load('products'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['message' => 'Gagal memperbarui paket: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a rental package
     */
    public function destroy(int $id)
    {
        $package = RentalPackage::findOrFail($id);
        
        // Soft delete or just inactivate. Let's just inactivate since rentals might depend on it
        // Or actually delete it if there's no soft deletes? RentalPackage doesn't use SoftDeletes
        // Let's just set is_active to false
        $package->update(['is_active' => false]);

        return response()->json([
            'message' => 'Paket Sewa dinonaktifkan.',
        ]);
    }
}
