<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\SubscriptionAccount;
use Illuminate\Http\Request;

class SubscriptionAccountController extends Controller
{
    public function index(Business $business)
    {
        $this->authorize('viewSubscriptionAccounts', $business);

        $accounts = $business->subscriptionAccounts()
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json([
            'message' => 'Subscription accounts retrieved successfully',
            'accounts' => $accounts
        ]);
    }

    public function show(Business $business, SubscriptionAccount $subscriptionAccount)
    {
        $this->authorize('viewSubscriptionAccounts', $business);

        if ($subscriptionAccount->business_id !== $business->id) {
            return response()->json([
                'message' => 'This subscription account does not belong to the specified business'
            ], 403);
        }

        return response()->json([
            'message' => 'Subscription account retrieved successfully',
            'account' => $subscriptionAccount
        ]);
    }

    // This method would typically be called by an external system or admin
    public function store(Request $request, Business $business)
    {
        // Only allow this method to be called by users with admin email domain
        if (!str_ends_with(auth()->user()->email, '@garante.admin')) {
            return response()->json([
                'message' => 'Unauthorized action'
            ], 403);
        }

        $validated = $request->validate([
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
                'unique:subscription_accounts,account_number,NULL,id,bank_name,' . $request->bank_name
            ],
            'external_id' => 'required|string|unique:subscription_accounts',
            'metadata' => 'nullable|array'
        ]);

        $subscriptionAccount = $business->subscriptionAccounts()->create($validated + [
            'status' => 'active'
        ]);

        return response()->json([
            'message' => 'Subscription account created successfully',
            'account' => $subscriptionAccount
        ], 201);
    }

    // This method would typically be called by an external system or admin
    public function update(Request $request, Business $business, SubscriptionAccount $subscriptionAccount)
    {
        // Only allow this method to be called by users with admin email domain
        if (!str_ends_with(auth()->user()->email, '@garante.admin')) {
            return response()->json([
                'message' => 'Unauthorized action'
            ], 403);
        }

        if ($subscriptionAccount->business_id !== $business->id) {
            return response()->json([
                'message' => 'This subscription account does not belong to the specified business'
            ], 403);
        }

        $validated = $request->validate([
            'status' => 'sometimes|required|string|in:active,inactive',
            'metadata' => 'sometimes|nullable|array'
        ]);

        $subscriptionAccount->update($validated);

        return response()->json([
            'message' => 'Subscription account updated successfully',
            'account' => $subscriptionAccount
        ]);
    }
}
