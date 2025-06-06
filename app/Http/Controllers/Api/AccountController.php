<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Validator;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    /**
     * List all accounts for a business.
     */
    public function index(Business $business)
    {
        $this->authorize('view', $business);

        return response()->json([
            'message' => 'Accounts retrieved successfully',
            'accounts' => $business->accounts
        ]);
    }

    /**
     * Request a new bank account for the business.
     */
    public function store(Request $request, Business $business)
    {
        $this->authorize('update', $business);

        $validator = Validator::make($request->all(), [
            'bank_name' => 'required|string|max:255',
            'account_name' => 'required|string|max:255',
            'account_number' => [
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
                'unique:accounts,account_number,NULL,id,bank_name,' . $request->bank_name
            ]
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $account = Account::create([
            'business_id' => $business->id,
            'bank_name' => $request->bank_name,
            'account_name' => $request->account_name,
            'account_number' => $request->account_number,
            'status' => 'active'
        ]);

        return response()->json([
            'message' => 'Account added successfully',
            'account' => $account
        ], 201);
    }

    /**
     * Get a specific account.
     */
    public function show(Business $business, Account $account)
    {
        $this->authorize('view', $account);

        if ($account->business_id !== $business->id) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        return response()->json([
            'message' => 'Account retrieved successfully',
            'account' => $account
        ]);
    }

    /**
     * Delete an account.
     */
    public function destroy(Business $business, Account $account)
    {
        $this->authorize('delete', $account);

        if ($account->business_id !== $business->id) {
            return response()->json([
                'message' => 'Account not found'
            ], 404);
        }

        // Here you would typically make a call to your external API to remove the account
        // This is a placeholder for the external API integration
        try {
            // $response = Http::delete('external-api/accounts/' . $account->external_id);
            
            // if (!$response->successful()) {
            //     return response()->json([
            //         'message' => 'Failed to remove account with bank'
            //     ], 422);
            // }

            $account->delete();

            return response()->json([
                'message' => 'Account removed successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Failed to remove account',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, Business $business, Account $account)
    {
        $this->authorize('update', $account);

        if ($account->business_id !== $business->id) {
            return response()->json([
                'message' => 'This account does not belong to the specified business'
            ], 403);
        }

        $validator = Validator::make($request->all(), [
            'bank_name' => 'sometimes|required|string|max:255',
            'account_name' => 'sometimes|required|string|max:255',
            'account_number' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                'regex:/^[0-9]+$/',
                'unique:accounts,account_number,' . $account->id . ',id,bank_name,' . ($request->bank_name ?? $account->bank_name)
            ],
            'status' => 'sometimes|required|string|in:active,inactive'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'errors' => $validator->errors()
            ], 422);
        }

        $account->update($request->only([
            'bank_name',
            'account_name',
            'account_number',
            'status'
        ]));

        return response()->json([
            'message' => 'Account updated successfully',
            'account' => $account
        ]);
    }
}
