<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guarantee;
use App\Models\Business;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GuaranteeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request)
    {
        $guarantees = Guarantee::where(function($query) {
            $query->where('seller_id', Auth::id())
                  ->orWhere('buyer_id', Auth::id());
        })
        ->when($request->status, function($query, $status) {
            return $query->where('status', $status);
        })
        ->when($request->type, function($query, $type) {
            if ($type === 'active') {
                return $query->where('status', 'active')
                           ->where(function($q) {
                               $q->whereNull('expires_at')
                                 ->orWhere('expires_at', '>', now());
                           });
            }
            if ($type === 'expired') {
                return $query->where('expires_at', '<=', now());
            }
        })
        ->latest()
        ->paginate(15);

        return response()->json($guarantees);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'business_id' => 'required|exists:businesses,id',
            'buyer_id' => 'required|exists:users,id',
            'service_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'terms' => 'required|array',
            'expires_at' => 'nullable|date|after:now'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $user = auth()->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json(['error' => 'You must have a profile to create guarantees'], 422);
        }

        // Ensure the authenticated user is a member of the business
        $business = Business::findOrFail($request->business_id);
        $isMember = $business->members()->where('profile_id', $profile->id)->exists();

        if (!$isMember) {
            return response()->json([
                'error' => 'You must be a member of the business to create guarantees',
                'debug' => [
                    'user_id' => $user->id,
                    'profile_id' => $profile->id,
                    'business_id' => $business->id,
                    'business_members' => $business->members()->pluck('profile_id')->toArray()
                ]
            ], 403);
        }

        // Create guarantee with authenticated user as seller
        $guarantee = Guarantee::create([
            'seller_id' => $user->id,
            'business_id' => $request->business_id,
            'buyer_id' => $request->buyer_id,
            'service_description' => $request->service_description,
            'price' => $request->price,
            'terms' => $request->terms,
            'expires_at' => $request->expires_at,
            'status' => 'draft'
        ]);

        // TODO: Trigger WhatsApp notification for consent
        // This would integrate with your WhatsApp API

        return response()->json([
            'message' => 'Guarantee created successfully',
            'guarantee' => $guarantee->load(['seller', 'buyer', 'business']),
        ], 201);
    }

    public function show(Guarantee $guarantee)
    {
        // Ensure the authenticated user is either the seller or buyer
        if (!in_array(auth()->id(), [$guarantee->seller_id, $guarantee->buyer_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'guarantee' => $guarantee->load(['seller', 'buyer', 'disputes']),
        ]);
    }

    public function updateStatus(Request $request, Guarantee $guarantee)
    {
        $user = auth()->user();

        // Ensure the authenticated user is either the seller or buyer
        if (!in_array($user->id, [$guarantee->seller_id, $guarantee->buyer_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validator = Validator::make($request->all(), [
            'status' => 'required|in:completed,cancelled,disputed'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        // Check if guarantee is expired
        if ($guarantee->isExpired()) {
            return response()->json([
                'message' => 'Cannot update status of expired guarantee'
            ], 422);
        }

        // Status-specific validations
        switch ($request->status) {
            case 'completed':
                // Only buyer can mark as completed
                if ($user->id !== $guarantee->buyer_id) {
                    return response()->json([
                        'message' => 'Only the buyer can mark a guarantee as completed'
                    ], 403);
                }

                // Require both consents before completion
                if (!$guarantee->hasConsent()) {
                    return response()->json([
                        'message' => 'Both parties must consent before marking as completed'
                    ], 422);
                }
                break;

            case 'cancelled':
                // Cannot cancel if already completed
                if ($guarantee->status === 'completed') {
                    return response()->json([
                        'message' => 'Cannot cancel a completed guarantee. You may raise a dispute instead.'
                    ], 422);
                }

                // Seller cannot cancel if buyer has already consented
                if ($user->id === $guarantee->seller_id && $guarantee->buyer_consent) {
                    return response()->json([
                        'message' => 'Cannot cancel after buyer has consented. You may raise a dispute if needed.'
                    ], 422);
                }

                // Buyer can cancel at any time before completion
                break;

            case 'disputed':
                // Both parties can raise a dispute at any time
                // Can dispute even if completed
                break;
        }

        $guarantee->update(['status' => $request->status]);

        return response()->json([
            'message' => 'Guarantee status updated successfully',
            'guarantee' => $guarantee->fresh(['seller', 'buyer']),
        ]);
    }

    public function consent(Request $request, Guarantee $guarantee)
    {
        $user = auth()->user();

        // Ensure the authenticated user is either the seller or buyer
        if (!in_array($user->id, [$guarantee->seller_id, $guarantee->buyer_id])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $field = $user->id === $guarantee->seller_id ? 'seller_consent' : 'buyer_consent';

        if ($guarantee->$field) {
            return response()->json([
                'message' => 'Consent already provided'
            ], 422);
        }

        if ($guarantee->isExpired()) {
            return response()->json([
                'message' => 'Cannot provide consent for expired guarantee'
            ], 422);
        }

        $guarantee->update([
            $field => true
        ]);

        if ($guarantee->hasConsent()) {
            $guarantee->update(['status' => 'active']);
        }

        return response()->json([
            'message' => 'Consent provided successfully',
            'guarantee' => $guarantee->fresh(['seller', 'buyer']),
        ]);
    }

    public function accept(Guarantee $guarantee)
    {
        $user = auth()->user();

        // Debug information
        \Log::info('Accept guarantee attempt', [
            'user_id' => $user->id,
            'buyer_id' => $guarantee->buyer_id,
            'guarantee' => $guarantee->toArray()
        ]);

        // Ensure the authenticated user is the buyer
        if ($user->id !== $guarantee->buyer_id) {
            return response()->json([
                'error' => 'Only the buyer can accept the guarantee',
                'debug' => [
                    'user_id' => $user->id,
                    'buyer_id' => $guarantee->buyer_id
                ]
            ], 403);
        }

        // Ensure the guarantee is in draft status
        if ($guarantee->status !== 'draft') {
            return response()->json(['error' => 'Guarantee can only be accepted when in draft status'], 422);
        }

        $guarantee->update([
            'status' => 'accepted',
            'accepted_at' => now()
        ]);

        return response()->json([
            'message' => 'Guarantee accepted successfully',
            'guarantee' => $guarantee->fresh(['seller', 'buyer', 'business']),
        ]);
    }
}
