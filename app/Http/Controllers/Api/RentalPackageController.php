<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

use App\Models\RentalPackage;

class RentalPackageController extends Controller
{
    public function index()
    {
        $packages = RentalPackage::with(['items.product.primaryImage'])->where('is_active', true)->get();
        return response()->json(['data' => $packages]);
    }

    public function show($slug)
    {
        $package = RentalPackage::with(['items.product.primaryImage'])->where('slug', $slug)->where('is_active', true)->firstOrFail();
        return response()->json(['data' => $package]);
    }
}
