<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class ProfileController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
    }

    public function store(Request $request)
    {
        // Check if user already has a profile first, before validation
        if (Auth::user()->profile) {
            return response()->json([
                'message' => 'User already has a profile',
                'error' => 'Only one profile per user is allowed'
            ], 422);
        }

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

        try {
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
        } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
            // Clean up stored files if profile creation fails
            if (isset($idDocPath)) {
                Storage::disk('private')->delete($idDocPath);
            }
            if (isset($addressDocPath)) {
                Storage::disk('private')->delete($addressDocPath);
            }

            return response()->json([
                'message' => 'User already has a profile',
                'error' => 'Only one profile per user is allowed'
            ], 422);
        }
    }

    public function show(Profile $profile)
    {
        $this->authorize('view', $profile);
        return response()->json(['profile' => $profile]);
    }

    public function update(Request $request, Profile $profile)
    {
        try {
            $this->authorize('update', $profile);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            if ($profile->hasUnresolvedDisputes()) {
                return response()->json([
                    'message' => 'Cannot update profile while there are unresolved disputes'
                ], 403);
            }
            throw $e;
        }

        $validator = Validator::make($request->all(), [
            'address' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'profession' => 'sometimes|required|string',
            'id_document' => 'sometimes|required|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'address_document' => 'sometimes|required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('id_document')) {
            // Delete old document
            if ($profile->id_document_url) {
                Storage::disk('private')->delete($profile->id_document_url);
            }
            
            // Store new document
            $idDocPath = $request->file('id_document')->store('documents/id', 'private');
            $profile->id_document_url = $idDocPath;
        }

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

    public function destroy(Request $request, Profile $profile)
    {
        $this->authorize('delete', $profile);

        $validator = Validator::make($request->all(), [
            'reason' => 'required|string|min:10|max:1000'
        ], [
            'reason.required' => 'A reason for deletion is required.',
            'reason.min' => 'The deletion reason must be at least 10 characters.',
            'reason.max' => 'The deletion reason cannot exceed 1000 characters.'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        DB::transaction(function () use ($profile, $request) {
            // Store deletion reason and soft delete the profile
            $profile->update(['deletion_reason' => $request->reason]);
            $profile->delete();

            // Soft delete the user as well
            $profile->user->delete();
        });

        return response()->json([
            'message' => 'Profile and user account have been deactivated successfully'
        ]);
    }
} 