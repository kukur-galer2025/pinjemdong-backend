<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

use Illuminate\Http\Request;

use App\Models\UserAddress;

class UserAddressController extends Controller
{
    public function index(Request $request)
    {
        return response()->json([
            'addresses' => $request->user()->addresses()->orderBy('created_at', 'desc')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $address = $request->user()->addresses()->create($validated);

        return response()->json([
            'message' => 'Alamat berhasil disimpan.',
            'address' => $address
        ], 201);
    }

    public function destroy(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);
        $address->delete();

        return response()->json([
            'message' => 'Alamat berhasil dihapus.'
        ]);
    }

    public function update(Request $request, $id)
    {
        $validated = $request->validate([
            'label' => 'required|string|max:255',
            'address' => 'required|string',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
        ]);

        $address = $request->user()->addresses()->findOrFail($id);
        $address->update($validated);

        return response()->json([
            'message' => 'Alamat berhasil diperbarui.',
            'address' => $address
        ]);
    }
}
