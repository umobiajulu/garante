<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Business;
use App\Models\Subscription;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SubscriptionController extends Controller
{
    // Flat rate amount in Naira
    private const SUBSCRIPTION_RATE = 5000.00;

    /**
     * Display a listing of the resource.
     */
    public function index(Business $business)
    {
        $this->authorize('viewSubscriptions', $business);

        $subscriptions = $business->subscriptions()
            ->with('creator')
            ->orderBy('end_date', 'desc')
            ->get();

        return response()->json([
            'message' => 'Subscriptions retrieved successfully',
            'subscriptions' => $subscriptions
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, Business $business)
    {
        // Only allow admin users to create subscriptions
        if (!str_ends_with(auth()->user()->email, '@garante.admin')) {
            return response()->json([
                'message' => 'Unauthorized action'
            ], 403);
        }

        $validated = $request->validate([
            'duration_months' => 'required|integer|min:1|max:60',
            'notes' => 'nullable|string|max:1000'
        ]);

        // Calculate start and end dates based on existing subscriptions
        $latestSubscription = $business->subscriptions()
            ->orderBy('end_date', 'desc')
            ->first();

        $startDate = now();
        if ($latestSubscription && $latestSubscription->end_date > now()) {
            $startDate = $latestSubscription->end_date;
        }

        $endDate = $startDate->copy()->addMonths($validated['duration_months']);

        // Calculate total amount based on duration and flat rate
        $amount = self::SUBSCRIPTION_RATE * $validated['duration_months'];

        DB::beginTransaction();
        try {
            $subscription = $business->subscriptions()->create([
                'duration_months' => $validated['duration_months'],
                'amount' => $amount,
                'start_date' => $startDate,
                'end_date' => $endDate,
                'created_by' => auth()->id(),
                'notes' => $validated['notes'] ?? null
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Subscription created successfully',
                'subscription' => $subscription->load('creator')
            ], 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'message' => 'Failed to create subscription'
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Business $business, Subscription $subscription)
    {
        $this->authorize('viewSubscriptions', $business);

        if ($subscription->business_id !== $business->id) {
            return response()->json([
                'message' => 'This subscription does not belong to the specified business'
            ], 403);
        }

        return response()->json([
            'message' => 'Subscription retrieved successfully',
            'subscription' => $subscription->load('creator')
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Business $business, Subscription $subscription)
    {
        // Only allow admin users to delete subscriptions
        if (!str_ends_with(auth()->user()->email, '@garante.admin')) {
            return response()->json([
                'message' => 'Unauthorized action'
            ], 403);
        }

        if ($subscription->business_id !== $business->id) {
            return response()->json([
                'message' => 'This subscription does not belong to the specified business'
            ], 403);
        }

        $subscription->delete();

        return response()->json([
            'message' => 'Subscription deleted successfully'
        ]);
    }
}
