<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BusinessInvitation extends Model
{
    protected $fillable = [
        'business_id',
        'profile_id',
        'role',
        'status',
        'expires_at',
        'responded_at'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'responded_at' => 'datetime'
    ];

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function profile(): BelongsTo
    {
        return $this->belongsTo(Profile::class);
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function isExpired(): bool
    {
        return $this->status === 'expired' || $this->expires_at->isPast();
    }

    public function isAccepted(): bool
    {
        return $this->status === 'accepted';
    }

    public function isRejected(): bool
    {
        return $this->status === 'rejected';
    }

    public function accept(): void
    {
        $this->update([
            'status' => 'accepted',
            'responded_at' => now()
        ]);
    }

    public function reject(): void
    {
        $this->update([
            'status' => 'rejected',
            'responded_at' => now()
        ]);
    }

    public function expire(): void
    {
        if ($this->isPending() && $this->expires_at->isPast()) {
            $this->update(['status' => 'expired']);
        }
    }
} 