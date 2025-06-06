<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Profile extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'nin',
        'bvn',
        'nin_verified',
        'bvn_verified',
        'nin_phone',
        'bvn_phone',
        'nin_dob',
        'bvn_dob',
        'address',
        'state',
        'city',
        'profession',
        'verification_status',
        'id_document_url',
        'address_document_url',
        'verified_by',
        'verified_at',
        'deletion_reason'
    ];

    protected $casts = [
        'nin_verified' => 'boolean',
        'bvn_verified' => 'boolean',
        'verified_at' => 'datetime',
        'nin_dob' => 'date',
        'bvn_dob' => 'date',
        'deleted_at' => 'datetime'
    ];

    protected $hidden = [
        'nin',
        'bvn',
        'nin_phone',
        'bvn_phone',
        'nin_dob',
        'bvn_dob',
        'id_document_url',
        'address_document_url'
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function verifier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }

    public function businesses(): BelongsToMany
    {
        return $this->belongsToMany(Business::class, 'business_members')
                    ->withPivot('role')
                    ->withTimestamps();
    }

    public function isVerified(): bool
    {
        return $this->verification_status === 'verified';
    }

    public function isPending(): bool
    {
        return $this->verification_status === 'pending';
    }

    public function canJoinBusiness(): bool
    {
        // Only verified profiles can join businesses
        if (!$this->isVerified()) {
            return false;
        }

        // Check if user owns any business
        $ownsABusiness = Business::where('owner_id', $this->user_id)->exists();
        if ($ownsABusiness) {
            return false;
        }

        // Check if profile is already a member of any business
        return !$this->businesses()->exists();
    }

    public function isNINVerified(): bool
    {
        return $this->nin_verified;
    }

    public function isBVNVerified(): bool
    {
        return $this->bvn_verified;
    }

    public function phoneMatchesVerification(): bool
    {
        $userPhone = $this->user->phone_number;
        return $userPhone && ($userPhone === $this->nin_phone || $userPhone === $this->bvn_phone);
    }

    public function dateOfBirthMatches(): bool
    {
        return $this->nin_dob && 
               $this->bvn_dob && 
               $this->nin_dob->equalTo($this->bvn_dob);
    }

    public function markNINVerified(): void
    {
        $this->update([
            'nin_verified' => true,
            'verified_at' => now()
        ]);
        $this->checkFullVerification();
    }

    public function markBVNVerified(): void
    {
        $this->update([
            'bvn_verified' => true,
            'verified_at' => now()
        ]);
        $this->checkFullVerification();
    }

    protected function checkFullVerification(): void
    {
        if ($this->nin_verified && 
            $this->bvn_verified && 
            $this->phoneMatchesVerification() && 
            $this->dateOfBirthMatches() &&
            $this->verification_status === 'pending') {
            $this->update([
                'verification_status' => 'verified',
                'verified_at' => now()
            ]);
        }
    }

    public function getVerificationStatus(): array
    {
        return [
            'nin_verified' => $this->nin_verified,
            'bvn_verified' => $this->bvn_verified,
            'phone_verified' => $this->phoneMatchesVerification(),
            'dob_verified' => $this->dateOfBirthMatches(),
            'verification_status' => $this->verification_status,
            'missing_requirements' => $this->getMissingRequirements()
        ];
    }

    protected function getMissingRequirements(): array
    {
        $missing = [];
        
        if (!$this->nin_verified) {
            $missing[] = 'NIN verification required';
        }
        
        if (!$this->bvn_verified) {
            $missing[] = 'BVN verification required';
        }
        
        if (!$this->phoneMatchesVerification()) {
            $missing[] = 'Phone number must match either NIN or BVN phone number';
        }

        if (!$this->dateOfBirthMatches()) {
            if (!$this->nin_dob || !$this->bvn_dob) {
                $missing[] = 'Both NIN and BVN date of birth are required';
            } else {
                $missing[] = 'NIN and BVN date of birth must match';
            }
        }
        
        return $missing;
    }

    public function isFullyVerified()
    {
        return $this->nin_verified &&
            $this->bvn_verified &&
            $this->verification_status === 'verified' &&
            $this->verified_at !== null &&
            $this->verified_by !== null;
    }

    public function hasUnresolvedDisputes(): bool
    {
        return $this->businesses()
            ->whereHas('guarantees', function ($query) {
                $query->whereHas('disputes', function ($q) {
                    $q->where('status', 'pending');
                });
            })
            ->exists();
    }
} 