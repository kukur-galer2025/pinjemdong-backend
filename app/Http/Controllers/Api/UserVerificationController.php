<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UserVerification;
use Illuminate\Http\Request;

class UserVerificationController extends Controller
{
    /**
     * Submit KYC verification
     */
    public function submit(Request $request)
    {
        $request->validate([
            'ktp_number' => 'required|string|size:16',
            'ktp_image' => 'required|image|max:5120',
            'selfie_image' => 'required|image|max:5120',
            'emergency_contact_name' => 'required|string|max:255',
            'emergency_contact_phone' => 'required|string|max:20',
            'address' => 'required|string|max:500',
            'latitude' => 'required|numeric',
            'longitude' => 'required|numeric',
            'save_as_primary_address' => 'nullable|boolean',
        ]);

        $ktpPath = $request->file('ktp_image')->store('kyc/ktp', 'public');
        $selfiePath = $request->file('selfie_image')->store('kyc/selfie', 'public');

        $verification = UserVerification::updateOrCreate(
            ['user_id' => $request->user()->id],
            [
                'ktp_number' => $request->ktp_number,
                'ktp_image' => $ktpPath,
                'selfie_image' => $selfiePath,
                'emergency_contact_name' => $request->emergency_contact_name,
                'emergency_contact_phone' => $request->emergency_contact_phone,
                'address' => $request->address,
                'status' => 'pending',
                'rejection_reason' => null,
            ]
        );

        if ($request->save_as_primary_address) {
            \App\Models\UserAddress::create([
                'user_id' => $request->user()->id,
                'label' => 'Alamat Utama',
                'address' => $request->address,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
            ]);
        }

        return response()->json([
            'message' => 'Verifikasi identitas berhasil dikirim! Menunggu persetujuan admin.',
            'verification' => $verification,
        ], 201);
    }

    /**
     * Get current verification status
     */
    public function status(Request $request)
    {
        $verification = $request->user()->verification;

        return response()->json([
            'verification' => $verification,
            'is_verified' => $verification && $verification->status === 'approved',
        ]);
    }
}
