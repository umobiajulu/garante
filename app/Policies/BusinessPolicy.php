<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class BusinessPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        // Admin users can do everything
        if (str_ends_with($user->email, '@garante.admin')) {
            return true;
        }
    }

    protected function getMemberRole(User $user, Business $business)
    {
        if (!$user->profile) {
            return null;
        }
        $member = $business->members()->where('profile_id', $user->profile->id)->first();
        return $member ? $member->pivot->role : null;
    }

    protected function isOwner(User $user, Business $business)
    {
        return $business->owner_id === $user->id;
    }

    public function view(User $user, Business $business)
    {
        // Users can view businesses they are members of or own
        return $this->isOwner($user, $business) || $this->getMemberRole($user, $business) !== null;
    }

    public function update(User $user, Business $business)
    {
        // Only owners can update business details
        if (!$this->isOwner($user, $business)) {
            return false;
        }

        // Check for unresolved disputes
        if ($business->hasUnresolvedDisputes()) {
            return false;
        }

        return true;
    }

    public function manageMember(User $user, Business $business)
    {
        // Only owners can manage members
        return $this->isOwner($user, $business);
    }

    public function verify(User $user, Business $business)
    {
        // Only arbitrators can verify businesses
        return str_ends_with($user->email, '@garante.arbitrator');
    }

    public function viewAccounts(User $user, Business $business)
    {
        // Any business member or owner can view accounts
        return $this->isOwner($user, $business) || $this->getMemberRole($user, $business) !== null;
    }

    public function manageAccounts(User $user, Business $business)
    {
        // Only owners can manage accounts
        return $this->isOwner($user, $business);
    }

    public function viewSubscriptionAccounts(User $user, Business $business)
    {
        // Any business member or owner can view subscription accounts
        return $this->isOwner($user, $business) || $this->getMemberRole($user, $business) !== null;
    }

    public function manageSubscriptionAccounts(User $user, Business $business)
    {
        // Only admin can manage subscription accounts
        return str_ends_with($user->email, '@garante.admin');
    }

    public function viewSubscriptions(User $user, Business $business)
    {
        // Any business member or owner can view subscriptions
        return $this->isOwner($user, $business) || $this->getMemberRole($user, $business) !== null;
    }

    public function manageSubscriptions(User $user, Business $business)
    {
        // Only admin can manage subscriptions
        return str_ends_with($user->email, '@garante.admin');
    }
} 