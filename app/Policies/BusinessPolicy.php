<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BusinessPolicy
{
    use HandlesAuthorization;

    public function view(User $user, Business $business)
    {
        // Users can view businesses they are members of
        return $business->members()->where('profile_id', $user->profile->id)->exists();
    }

    public function update(User $user, Business $business)
    {
        // Only owners can update business details
        $member = $business->members()->where('profile_id', $user->profile->id)->first();
        return $member && $member->pivot->role === 'owner';
    }

    public function manageMember(User $user, Business $business)
    {
        // Only owners can manage members
        $member = $business->members()->where('profile_id', $user->profile->id)->first();
        return $member && $member->pivot->role === 'owner';
    }

    public function verify(User $user, Business $business)
    {
        // Only arbitrators can verify businesses
        return $user->hasRole('arbitrator');
    }
} 