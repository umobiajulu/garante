<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Verdict extends Model
{
    use HasFactory;

    protected $fillable = [
        'dispute_id',
        'arbitrator_id',
        'decision',
        'refund_amount',
        'notes',
        'evidence_reviewed',
        'decided_at'
    ];

    protected $casts = [
        'refund_amount' => 'decimal:2',
        'evidence_reviewed' => 'array',
        'decided_at' => 'datetime'
    ];

    public function dispute(): BelongsTo
    {
        return $this->belongsTo(Dispute::class);
    }

    public function arbitrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'arbitrator_id');
    }

    public function guarantee(): BelongsTo
    {
        return $this->belongsTo(Guarantee::class);
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_id');
    }

    public function resolver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isRestitutionRequired(): bool
    {
        return $this->refund_amount > 0;
    }

    public function isRestitutionOverdue(): bool
    {
        return $this->isRestitutionRequired() && 
               $this->decided_at && 
               $this->decided_at->isPast() &&
               $this->status !== 'completed';
    }
} 