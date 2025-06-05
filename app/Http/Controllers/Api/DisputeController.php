<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Dispute;
use App\Models\Guarantee;
use App\Models\User;
use App\Models\Restitution;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class DisputeController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth:sanctum');
    }

    public function index()
    {
        $user = Auth::user();
        
        if ($user->isArbitrator()) {
            $disputes = Dispute::with('guarantee', 'initiator')
                ->where('status', '!=', Dispute::STATUS_RESOLVED)
                ->get();
        } else {
            $disputes = Dispute::with('guarantee', 'initiator')
                ->whereHas('guarantee', function ($query) use ($user) {
                    $query->where('seller_id', $user->id)
                        ->orWhere('buyer_id', $user->id);
                })
                ->get();
        }

        return response()->json([
            'message' => 'Disputes retrieved successfully',
            'disputes' => $disputes
        ]);
    }

    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'guarantee_id' => 'required|exists:guarantees,id',
            'reason' => 'required|string',
            'description' => 'required|string',
            'evidence' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $guarantee = Guarantee::findOrFail($request->guarantee_id);

        // Only buyer can initiate dispute
        if (Auth::id() !== $guarantee->buyer_id) {
            return response()->json([
                'message' => 'Only buyer can initiate dispute'
            ], 403);
        }

        // Check if guarantee already has an active dispute
        if ($guarantee->hasActiveDispute()) {
            return response()->json([
                'message' => 'Guarantee already has an active dispute'
            ], 422);
        }

        $dispute = Dispute::create([
            'guarantee_id' => $request->guarantee_id,
            'initiated_by' => Auth::id(),
            'reason' => $request->reason,
            'description' => $request->description,
            'evidence' => $request->evidence,
            'status' => Dispute::STATUS_PENDING
        ]);

        // Update guarantee status
        $guarantee->update(['status' => 'disputed']);

        return response()->json([
            'message' => 'Dispute created successfully',
            'dispute' => $dispute->load('guarantee', 'initiator')
        ], 201);
    }

    public function show(Dispute $dispute)
    {
        $user = Auth::user();
        $guarantee = $dispute->guarantee;

        // Only seller, buyer, or arbitrator can view dispute
        if (!in_array($user->id, [$guarantee->seller_id, $guarantee->buyer_id]) && !$user->isArbitrator()) {
            return response()->json([
                'message' => 'Unauthorized to view this dispute'
            ], 403);
        }

        return response()->json([
            'message' => 'Dispute details retrieved successfully',
            'dispute' => $dispute->load('guarantee', 'initiator')
        ]);
    }

    public function submitDefense(Request $request, Dispute $dispute)
    {
        $user = Auth::user();
        $guarantee = $dispute->guarantee;

        // Only the non-initiator can submit defense
        if ($user->id === $dispute->initiated_by) {
            return response()->json([
                'message' => 'Cannot submit defense for a dispute you initiated'
            ], 403);
        }

        // Only seller or buyer can submit defense
        if (!in_array($user->id, [$guarantee->seller_id, $guarantee->buyer_id])) {
            return response()->json([
                'message' => 'Only seller or buyer can submit defense'
            ], 403);
        }

        // Check if dispute can accept defense
        if (!$dispute->canSubmitDefense()) {
            return response()->json([
                'message' => 'Defense cannot be submitted at this stage'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'defense_description' => 'required|string',
            'defense' => 'required|array'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $dispute->update([
            'defense_description' => $request->defense_description,
            'defense' => $request->defense,
            'status' => Dispute::STATUS_IN_REVIEW
        ]);

        return response()->json([
            'message' => 'Defense submitted successfully',
            'dispute' => $dispute->fresh()
        ]);
    }

    public function resolve(Request $request, Dispute $dispute)
    {
        if (!Auth::user()->isArbitrator()) {
            return response()->json([
                'message' => 'Only arbitrators can resolve disputes'
            ], 403);
        }

        if (!$dispute->canBeResolved()) {
            return response()->json([
                'message' => 'Dispute cannot be resolved at this stage'
            ], 422);
        }

        $validator = Validator::make($request->all(), [
            'decision' => 'required|in:refund,partial_refund,no_refund',
            'refund_amount' => 'required_if:decision,partial_refund|numeric|min:0',
            'notes' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json($validator->errors(), 422);
        }

        $dispute->update([
            'status' => Dispute::STATUS_RESOLVED,
            'resolution_notes' => $request->notes,
            'resolved_by' => Auth::id(),
            'resolved_at' => now()
        ]);

        // Create verdict
        $verdict = $dispute->guarantee->verdicts()->create([
            'dispute_id' => $dispute->id,
            'guarantee_id' => $dispute->guarantee_id,
            'arbitrator_id' => Auth::id(),
            'decision' => $request->decision,
            'refund_amount' => $request->decision === 'partial_refund' ? $request->refund_amount : null,
            'notes' => $request->notes,
            'evidence_reviewed' => [
                'evidence' => $dispute->evidence,
                'defense' => $dispute->defense
            ],
            'decided_at' => now()
        ]);

        // Update trust scores based on decision
        $this->updateTrustScores($dispute, $request->decision);

        // Create restitution if needed
        if (in_array($request->decision, ['refund', 'partial_refund'])) {
            $amount = $request->decision === 'refund' 
                ? $dispute->guarantee->price 
                : $request->refund_amount;

            $restitution = Restitution::create([
                'verdict_id' => $verdict->id,
                'amount' => $amount,
                'status' => 'pending'
            ]);

            return response()->json([
                'message' => 'Dispute resolved successfully',
                'verdict' => $verdict,
                'restitution' => $restitution
            ]);
        }

        return response()->json([
            'message' => 'Dispute resolved successfully',
            'verdict' => $verdict
        ]);
    }

    protected function updateTrustScores(Dispute $dispute, string $decision)
    {
        $guarantee = $dispute->guarantee;
        $seller = User::find($guarantee->seller_id);

        // Determine trust score reduction based on decision
        $trustScoreReduction = match($decision) {
            'refund' => 50,
            'partial_refund' => 20,
            'no_refund' => 0,
        };

        // Only reduce seller's trust score as they are the one being found at fault
        if ($trustScoreReduction > 0) {
            $seller->trust_score = max(0, $seller->trust_score - $trustScoreReduction);
            $seller->save();
        }
    }
}
