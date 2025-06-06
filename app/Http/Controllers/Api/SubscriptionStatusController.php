<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Business;

class SubscriptionStatusController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function check(Request $request)
    {
        $user = $request->user();
        $profile = $user->profile;

        if (!$profile) {
            return response()->json([
                'message' => 'User does not have a profile',
                'has_active_subscription' => false,
                'details' => null
            ], 404);
        }

        // Get the user's business (if any)
        $business = $profile->businesses()->first();

        if (!$business) {
            return response()->json([
                'message' => 'User is not a member of any business',
                'has_active_subscription' => false,
                'details' => null
            ], 404);
        }

        $hasExpired = $business->hasSubscriptionExpired();
        $latestSubscription = $business->subscriptions()
            ->latest('end_date')
            ->first();

        return response()->json([
            'message' => $hasExpired ? 'Business subscription has expired' : 'Business has an active subscription',
            'has_active_subscription' => !$hasExpired,
            'details' => $latestSubscription ? [
                'business_name' => $business->name,
                'business_id' => $business->id,
                'subscription_id' => $latestSubscription->id,
                'start_date' => $latestSubscription->start_date,
                'end_date' => $latestSubscription->end_date,
                'duration_months' => $latestSubscription->duration_months,
                'amount' => $latestSubscription->amount,
            ] : null
        ]);
    }
} 