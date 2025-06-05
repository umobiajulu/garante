<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Guarantee;
use App\Models\Verdict;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class VerdictController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:api');
        $this->middleware('role:arbitrator')->except(['show', 'confirmRestitution']);
    }

    public function store(Request $request, Guarantee $guarantee)
    {
        // Validate that the guarantee is in disputed status
        if ($guarantee->status !== 'disputed') {
            return response()->json([
                'message' => 'Can only create verdict for disputed guarantees'
            ], 422);
        }

        // Check if verdict already exists
        if ($guarantee->verdict) {
            return response()->json([
                'message' => 'Verdict already exists for this guarantee'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'winner_id' => 'required|exists:users,id|in:' . $guarantee->seller_id . ',' . $guarantee->buyer_id,
            'verdict_summary' => 'required|string',
            'restitution_amount' => 'required|numeric|min:0',
            'restitution_due_date' => 'required_if:restitution_amount,>0|nullable|date|after:now',
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $verdict = Verdict::create([
            'guarantee_id' => $guarantee->id,
            'winner_id' => $request->winner_id,
            'verdict_summary' => $request->verdict_summary,
            'restitution_amount' => $request->restitution_amount,
            'restitution_due_date' => $request->restitution_due_date,
            'resolved_by' => Auth::id(),
            'status' => $request->restitution_amount > 0 ? 'pending_restitution' : 'completed'
        ]);

        // If seller wins and no restitution is required, mark guarantee as completed
        if ($request->winner_id === $guarantee->seller_id && !$verdict->isRestitutionRequired()) {
            $guarantee->update(['status' => 'completed']);
        }

        return response()->json([
            'message' => 'Verdict created successfully',
            'verdict' => $verdict->load(['guarantee', 'winner', 'resolver'])
        ], 201);
    }

    public function show(Verdict $verdict)
    {
        $guarantee = $verdict->guarantee;
        
        // Ensure the authenticated user is either the seller, buyer, or arbitrator
        if (!in_array(Auth::id(), [$guarantee->seller_id, $guarantee->buyer_id, $verdict->resolved_by])) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json([
            'verdict' => $verdict->load(['guarantee', 'winner', 'resolver'])
        ]);
    }

    public function confirmRestitution(Verdict $verdict)
    {
        $guarantee = $verdict->guarantee;
        
        // Only arbitrator or winner can confirm restitution
        if (Auth::id() !== $verdict->resolved_by && Auth::id() !== $verdict->winner_id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        if (!$verdict->isRestitutionRequired()) {
            return response()->json([
                'message' => 'No restitution required for this verdict'
            ], 422);
        }

        if ($verdict->status === 'completed') {
            return response()->json([
                'message' => 'Restitution already completed'
            ], 422);
        }

        $verdict->update(['status' => 'completed']);

        // If buyer won and restitution is now complete, update guarantee status
        if ($verdict->winner_id === $guarantee->buyer_id) {
            $guarantee->update(['status' => 'completed']);
        }

        return response()->json([
            'message' => 'Restitution confirmed successfully',
            'verdict' => $verdict->fresh(['guarantee', 'winner', 'resolver'])
        ]);
    }
} 