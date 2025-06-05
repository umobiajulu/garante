<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'nin' => 'required|string|unique:profiles',
            'bvn' => 'required|string|unique:profiles',
            'address' => 'required|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'profession' => 'required|string',
            'id_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'address_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Check if user already has a profile
        if (Auth::user()->profile) {
            return response()->json([
                'message' => 'Profile already exists'
            ], 422);
        }

        // Store documents
        $idDocPath = $request->file('id_document')->store('documents/id', 'private');
        $addressDocPath = $request->file('address_document')->store('documents/address', 'private');

        $profile = Profile::create([
            'user_id' => Auth::id(),
            'nin' => $request->nin,
            'bvn' => $request->bvn,
            'address' => $request->address,
            'state' => $request->state,
            'city' => $request->city,
            'profession' => $request->profession,
            'id_document_url' => $idDocPath,
            'address_document_url' => $addressDocPath,
            'verification_status' => 'pending'
        ]);

        return response()->json([
            'message' => 'Profile created successfully',
            'profile' => $profile
        ], 201);
    }

    public function show(Profile $profile)
    {
        $this->authorize('view', $profile);
        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request, Profile $profile)
    {
        $this->authorize('update', $profile);

        $validator = Validator::make($request->all(), [
            'address' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'profession' => 'sometimes|required|string',
            'address_document' => 'sometimes|required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Handle new address document if provided
        if ($request->hasFile('address_document')) {
            // Delete old document
            if ($profile->address_document_url) {
                Storage::disk('private')->delete($profile->address_document_url);
            }
            
            // Store new document
            $addressDocPath = $request->file('address_document')->store('documents/address', 'private');
            $profile->address_document_url = $addressDocPath;
        }

        $profile->fill($request->only(['address', 'state', 'city', 'profession']));
        $profile->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'profile' => $profile
        ]);
    }

    public function verify(Request $request, Profile $profile)
    {
        $this->authorize('verify', $profile);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $profile->update([
            'verification_status' => $request->status,
            'verified_at' => now(),
            'verified_by' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Profile verification status updated',
            'profile' => $profile
        ]);
    }
} 