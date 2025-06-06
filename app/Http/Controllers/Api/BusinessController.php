<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Profile;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class BusinessController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request)
    {
        // Check if user has a verified profile
        $profile = Auth::user()->profile;
        if (!$profile || !$profile->isVerified()) {
            return response()->json([
                'message' => 'Must have a verified profile to create a business'
            ], 422);
        }

        // Check if user can create/join a business
        if (!$profile->canJoinBusiness()) {
            return response()->json([
                'message' => 'You are already a member or owner of another business'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|unique:businesses',
            'business_type' => 'required|in:sole_proprietorship,partnership,limited_company',
            'address' => 'required|string',
            'state' => 'required|string',
            'city' => 'required|string',
            'registration_document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Store registration document
        $docPath = $request->file('registration_document')->store('documents/business', 'private');

        $business = Business::create([
            'name' => $request->name,
            'registration_number' => $request->registration_number,
            'business_type' => $request->business_type,
            'address' => $request->address,
            'state' => $request->state,
            'city' => $request->city,
            'owner_id' => Auth::id(),
            'registration_document_url' => $docPath,
            'verification_status' => 'pending'
        ]);

        // Add owner as a member with owner role
        $business->members()->attach($profile->id, ['role' => 'owner']);

        return response()->json([
            'message' => 'Business created successfully',
            'business' => $business->load('members')
        ], 201);
    }

    public function show(Business $business)
    {
        $this->authorize('view', $business);
        return response()->json([
            'message' => 'Business details retrieved successfully',
            'business' => $business->load('members', 'owner')
        ]);
    }

    public function update(Request $request, Business $business)
    {
        try {
            $this->authorize('update', $business);
        } catch (\Illuminate\Auth\Access\AuthorizationException $e) {
            if ($business->hasUnresolvedDisputes()) {
                return response()->json([
                    'message' => 'Cannot update business details while there are unresolved disputes'
                ], 403);
            }
            throw $e;
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'address' => 'sometimes|required|string',
            'state' => 'sometimes|required|string',
            'city' => 'sometimes|required|string',
            'registration_document' => 'sometimes|required|file|mimes:pdf,jpg,jpeg,png|max:5120',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        if ($request->hasFile('registration_document')) {
            // Delete old document
            if ($business->registration_document_url) {
                Storage::disk('private')->delete($business->registration_document_url);
            }
            
            // Store new document
            $docPath = $request->file('registration_document')->store('documents/business', 'private');
            $business->registration_document_url = $docPath;
        }

        $business->fill($request->only(['name', 'address', 'state', 'city']));
        $business->save();

        return response()->json([
            'message' => 'Business updated successfully',
            'business' => $business
        ]);
    }

    public function addMember(Request $request, Business $business)
    {
        $this->authorize('manageMember', $business);

        $validator = Validator::make($request->all(), [
            'user_id' => 'required|exists:users,id',
            'role' => 'required|in:manager,staff'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = User::findOrFail($request->user_id);
        $profile = $user->profile;

        if (!$profile) {
            return response()->json([
                'message' => 'User does not have a profile'
            ], 422);
        }

        // Check if profile is verified
        if (!$profile->isVerified()) {
            return response()->json([
                'message' => 'Only verified profiles can be added to a business'
            ], 422);
        }

        // Check if profile is already a member of another business
        if (!$profile->canJoinBusiness()) {
            return response()->json([
                'message' => 'Profile is already a member of another business'
            ], 422);
        }

        // Check if business can add more members
        if (!$business->canAddMember()) {
            return response()->json([
                'message' => 'Business has reached maximum member limit or is not verified'
            ], 422);
        }

        $business->members()->attach($profile->id, ['role' => $request->role]);

        return response()->json([
            'message' => 'Member added successfully',
            'member' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $request->role
            ]
        ], 201);
    }

    public function removeMember(Request $request, Business $business)
    {
        $this->authorize('manageMember', $business);

        $validator = Validator::make($request->all(), [
            'profile_id' => 'required|exists:profiles,id'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Cannot remove the owner
        $member = $business->members()->where('profile_id', $request->profile_id)->first();
        if (!$member || $member->pivot->role === 'owner') {
            return response()->json([
                'message' => 'Cannot remove the business owner'
            ], 422);
        }

        $business->members()->detach($request->profile_id);

        return response()->json([
            'message' => 'Member removed successfully',
            'business' => $business->load('members')
        ]);
    }

    public function verify(Request $request, Business $business)
    {
        $this->authorize('verify', $business);

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:verified,rejected'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $business->update([
            'verification_status' => $request->status,
            'verified_at' => now(),
            'verified_by' => Auth::id()
        ]);

        return response()->json([
            'message' => 'Business verification status updated',
            'business' => $business
        ]);
    }

    public function leaveBusiness(Request $request, Business $business)
    {
        $profile = Auth::user()->profile;
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        $leaveCheck = $business->canMemberLeave($profile);
        if (!$leaveCheck['can_leave']) {
            return response()->json([
                'message' => $leaveCheck['reason']
            ], 422);
        }

        $business->members()->detach($profile->id);

        return response()->json([
            'message' => 'Successfully left the business',
            'business' => $business->load('members')
        ]);
    }

    public function listMembers(Business $business)
    {
        $this->authorize('view', $business);

        return response()->json([
            'message' => 'Business members retrieved successfully',
            'members' => $business->members()->with('user')->get()->map(function ($member) {
                return [
                    'id' => $member->user->id,
                    'name' => $member->user->name,
                    'email' => $member->user->email,
                    'role' => $member->pivot->role
                ];
            })
        ]);
    }
} 