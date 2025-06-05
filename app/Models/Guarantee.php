<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Guarantee extends Model
{
    use HasFactory;

    protected $fillable = [
        'seller_id',
        'buyer_id',
        'business_id',
        'service_description',
        'price',
        'terms',
        'status',
        'expires_at',
        'seller_consent',
        'buyer_consent'
    ];

    protected $casts = [
        'terms' => 'array',
        'price' => 'decimal:2',
        'seller_consent' => 'boolean',
        'buyer_consent' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function business(): BelongsTo
    {
        return $this->belongsTo(Business::class);
    }

    public function disputes(): HasMany
    {
        return $this->hasMany(Dispute::class);
    }

    public function verdicts(): HasMany
    {
        return $this->hasMany(Verdict::class);
    }

    public function verdict(): HasOne
    {
        return $this->hasOne(Verdict::class);
    }

    public function isExpired(): bool
    {
        return $this->expires_at && $this->expires_at->isPast();
    }

    public function hasConsent(): bool
    {
        return $this->seller_consent && $this->buyer_consent;
    }

    public function hasVerdict(): bool
    {
        return $this->verdict()->exists();
    }

    public function isPendingRestitution(): bool
    {
        return $this->hasVerdict() && 
               $this->verdict->status === 'pending_restitution';
    }

    public function hasUnresolvedDispute(): bool
    {
        return $this->disputes()
                    ->where('status', 'pending')
                    ->exists();
    }

    public function canBeDisputed(): bool
    {
        return $this->status === 'accepted' && !$this->hasUnresolvedDispute();
    }

    public function hasActiveDispute()
    {
        return $this->disputes()
            ->whereIn('status', [Dispute::STATUS_PENDING, Dispute::STATUS_IN_REVIEW])
            ->exists();
    }
}
