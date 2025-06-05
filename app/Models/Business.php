<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Business extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'registration_number',
        'business_type',
        'address',
        'state',
        'city',
        'owner_id',
        'verification_status',
        'registration_document_url',
        'verified_at',
        'verified_by',
        'trust_score'
    ];

    protected $casts = [
        'verified_at' => 'datetime',
        'trust_score' => 'integer'
    ];

    protected $hidden = [
        'registration_document_url'
    ];

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function members(): BelongsToMany
    {
        return $this->belongsToMany(Profile::class, 'business_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function guarantees(): HasMany
    {
        return $this->hasMany(Guarantee::class);
    }

    public function guaranteeTemplates()
    {
        return $this->hasMany(GuaranteeTemplate::class);
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function canAddMember(): bool
    {
        return $this->isVerified() && $this->members()->count() < 10; // Limit of 10 members per business
    }

    public function hasUnresolvedDisputes(): bool
    {
        return $this->guarantees()
                    ->whereHas('disputes', function ($query) {
                        $query->where('status', 'pending');
                    })
                    ->exists();
    }

    public function canMemberLeave(Profile $profile): array
    {
        if ($this->owner_id === $profile->user_id) {
            return [
                'can_leave' => false,
                'reason' => 'Business owner cannot leave the business'
            ];
        }

        if ($this->hasUnresolvedDisputes()) {
            return [
                'can_leave' => false,
                'reason' => 'Cannot leave business with unresolved disputes'
            ];
        }

        $member = $this->members()->where('profile_id', $profile->id)->first();
        if (!$member) {
            return [
                'can_leave' => false,
                'reason' => 'Not a member of this business'
            ];
        }

        return [
            'can_leave' => true,
            'reason' => null
        ];
    }
} 