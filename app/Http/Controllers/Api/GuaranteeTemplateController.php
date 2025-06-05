<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\GuaranteeTemplate;
use App\Models\Business;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class GuaranteeTemplateController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index(Request $request, Business $business)
    {
        $user = Auth::user();
        
        // Check if user is a member of the business
        if (!$business->members()->where('profile_id', $user->profile->id)->exists() &&
            $business->owner_id !== $user->profile->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $templates = $business->guaranteeTemplates()->latest()->get();
        return response()->json($templates);
    }

    public function store(Request $request, Business $business)
    {
        // Check if user is the owner of the business
        if ($business->owner_id !== Auth::user()->profile->id) {
            return response()->json(['error' => 'Only business owner can create templates'], 403);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'service_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'terms' => 'required|array',
            'expires_in_days' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $template = GuaranteeTemplate::create([
            'business_id' => $business->id,
            'created_by' => Auth::id(),
            'name' => $request->name,
            'service_description' => $request->service_description,
            'price' => $request->price,
            'terms' => $request->terms,
            'expires_in_days' => $request->expires_in_days,
        ]);

        return response()->json([
            'message' => 'Template created successfully',
            'template' => $template
        ], 201);
    }

    public function show(Business $business, GuaranteeTemplate $template)
    {
        // Check if user is a member of the business
        if (!$business->members()->where('profile_id', Auth::user()->profile->id)->exists()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if ($template->business_id !== $business->id) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        return response()->json($template);
    }

    public function update(Request $request, Business $business, GuaranteeTemplate $template)
    {
        // Check if user is the owner of the business
        if ($business->owner_id !== Auth::user()->profile->id) {
            return response()->json(['error' => 'Only business owner can update templates'], 403);
        }

        if ($template->business_id !== $business->id) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'service_description' => 'required|string',
            'price' => 'required|numeric|min:0',
            'terms' => 'required|array',
            'expires_in_days' => 'nullable|integer|min:1'
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $template->update($request->all());

        return response()->json([
            'message' => 'Template updated successfully',
            'template' => $template
        ]);
    }

    public function destroy(Business $business, GuaranteeTemplate $template)
    {
        // Check if user is the owner of the business
        if ($business->owner_id !== Auth::user()->profile->id) {
            return response()->json(['error' => 'Only business owner can delete templates'], 403);
        }

        if ($template->business_id !== $business->id) {
            return response()->json(['error' => 'Template not found'], 404);
        }

        $template->delete();

        return response()->json([
            'message' => 'Template deleted successfully'
        ]);
    }
} 