<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class VerificationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function verifyNIN(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nin' => 'required|string|size:11',
            'nin_phone' => 'required|string',
            'nin_dob' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $profile = Auth::user()->profile;
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        if ($profile->isNINVerified()) {
            return response()->json([
                'message' => 'NIN already verified'
            ], 422);
        }

        try {
            // TODO: Implement actual NIN verification API call
            // For now, we'll just store the NIN and mock data
            $profile->update([
                'nin' => $request->nin,
                'nin_phone' => $request->nin_phone,
                'nin_dob' => $request->nin_dob,
                'nin_verified' => true
            ]);

            return response()->json([
                'message' => 'NIN verified successfully',
                'profile' => $profile->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'NIN verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyBVN(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'bvn' => 'required|string|size:11',
            'bvn_phone' => 'required|string',
            'bvn_dob' => 'required|date'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $profile = Auth::user()->profile;
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        if ($profile->isBVNVerified()) {
            return response()->json([
                'message' => 'BVN already verified'
            ], 422);
        }

        try {
            // TODO: Implement actual BVN verification API call
            // For now, we'll just store the BVN and mock data
            $profile->update([
                'bvn' => $request->bvn,
                'bvn_phone' => $request->bvn_phone,
                'bvn_dob' => $request->bvn_dob,
                'bvn_verified' => true
            ]);

            return response()->json([
                'message' => 'BVN verified successfully',
                'profile' => $profile->fresh()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'BVN verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyNINOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $profile = Auth::user()->profile;
        if (!$profile || !$profile->nin) {
            return response()->json([
                'message' => 'NIN not found or not validated'
            ], 404);
        }

        try {
            // TODO: Implement actual OTP verification
            // For now, we'll just mark it as verified
            $profile->markNINVerified();

            return response()->json([
                'message' => 'NIN verified successfully',
                'verification_status' => $profile->getVerificationStatus()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'NIN OTP verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function verifyBVNOTP(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $profile = Auth::user()->profile;
        if (!$profile || !$profile->bvn) {
            return response()->json([
                'message' => 'BVN not found or not validated'
            ], 404);
        }

        try {
            // TODO: Implement actual OTP verification
            // For now, we'll just mark it as verified
            $profile->markBVNVerified();

            return response()->json([
                'message' => 'BVN verified successfully',
                'verification_status' => $profile->getVerificationStatus()
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'message' => 'BVN OTP verification failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function getVerificationStatus()
    {
        $profile = Auth::user()->profile;
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        return response()->json([
            'verification_status' => $profile->getVerificationStatus()
        ]);
    }
} 