<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusinessInvitationController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function store(Request $request, Business $business)
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
                'message' => 'Only verified profiles can be invited to a business'
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

        // Check for existing pending invitation
        $existingInvitation = BusinessInvitation::where('business_id', $business->id)
            ->where('profile_id', $profile->id)
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation) {
            return response()->json([
                'message' => 'An invitation is already pending for this user'
            ], 422);
        }

        $invitation = BusinessInvitation::create([
            'business_id' => $business->id,
            'profile_id' => $profile->id,
            'role' => $request->role,
            'status' => 'pending',
            'expires_at' => now()->addDays(7)
        ]);

        return response()->json([
            'message' => 'Invitation sent successfully',
            'invitation' => $invitation
        ], 201);
    }

    public function accept(BusinessInvitation $invitation)
    {
        $this->authorize('respond', $invitation);

        if (!$invitation->isPending()) {
            return response()->json([
                'message' => 'Invitation is no longer pending'
            ], 422);
        }

        if ($invitation->isExpired()) {
            return response()->json([
                'message' => 'Invitation has expired'
            ], 422);
        }

        // Check if profile can still join the business
        if (!$invitation->profile->canJoinBusiness()) {
            $invitation->reject(); // Automatically reject if profile can't join
            return response()->json([
                'message' => 'Profile is already a member of another business'
            ], 422);
        }

        DB::transaction(function () use ($invitation) {
            // Accept the invitation
            $invitation->accept();

            // Add the profile to the business
            $invitation->business->members()->attach($invitation->profile_id, [
                'role' => $invitation->role
            ]);
        });

        return response()->json([
            'message' => 'Invitation accepted successfully'
        ]);
    }

    public function reject(BusinessInvitation $invitation)
    {
        $this->authorize('respond', $invitation);

        if (!$invitation->isPending()) {
            return response()->json([
                'message' => 'Invitation is no longer pending'
            ], 422);
        }

        $invitation->reject();

        return response()->json([
            'message' => 'Invitation rejected successfully'
        ]);
    }

    public function index(Request $request)
    {
        $profile = $request->user()->profile;
        
        if (!$profile) {
            return response()->json([
                'message' => 'Profile not found'
            ], 404);
        }

        $invitations = BusinessInvitation::where('profile_id', $profile->id)
            ->where('status', 'pending')
            ->with('business')
            ->get();

        // Check and update expired invitations
        $invitations->each(function ($invitation) {
            $invitation->expire();
        });

        // Refresh the collection to get updated statuses
        $invitations = BusinessInvitation::where('profile_id', $profile->id)
            ->where('status', 'pending')
            ->with('business')
            ->get();

        return response()->json([
            'invitations' => $invitations
        ]);
    }
} 