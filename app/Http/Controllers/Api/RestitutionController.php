<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Restitution;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class RestitutionController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function process(Request $request, Restitution $restitution)
    {
        $verdict = $restitution->verdict;
        $guarantee = $verdict->guarantee;

        // Only seller can process restitution
        if (Auth::id() !== $guarantee->seller_id) {
            return response()->json([
                'message' => 'Only seller can process restitution'
            ], 403);
        }

        // Check if restitution can be processed
        if (!$restitution->canBeProcessed()) {
            return response()->json([
                'message' => 'Restitution cannot be processed at this stage'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'proof_of_payment' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $restitution->update([
            'status' => 'processed',
            'proof_of_payment' => $request->proof_of_payment,
            'processed_at' => now()
        ]);

        return response()->json([
            'message' => 'Restitution processed successfully',
            'restitution' => $restitution->fresh()
        ]);
    }

    public function complete(Restitution $restitution)
    {
        $verdict = $restitution->verdict;
        $guarantee = $verdict->guarantee;
        $user = Auth::user();

        // Only buyer or arbitrator can complete restitution
        if ($user->id !== $guarantee->buyer_id && !$user->isArbitrator()) {
            return response()->json([
                'message' => 'Only buyer or arbitrator can complete restitution'
            ], 403);
        }

        // Check if restitution can be completed
        if (!$restitution->canBeCompleted()) {
            return response()->json([
                'message' => 'Restitution cannot be completed at this stage'
            ], 422);
        }

        $restitution->update([
            'status' => 'completed',
            'completed_by' => $user->id,
            'completed_at' => now()
        ]);

        // Restore trust score based on verdict decision
        $seller = User::find($guarantee->seller_id);
        $trustScoreRestoration = match($verdict->decision) {
            'refund' => 50,
            'partial_refund' => 20,
            'no_refund' => 0,
        };

        if ($trustScoreRestoration > 0) {
            $seller->trust_score = min(100, $seller->trust_score + $trustScoreRestoration);
            $seller->save();
        }

        return response()->json([
            'message' => 'Restitution completed successfully',
            'restitution' => $restitution->fresh()
        ]);
    }
} 