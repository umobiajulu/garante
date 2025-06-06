<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessSubscriptionPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    public function viewSubscriptions(User $user, Business $business): bool
    {
        // Admin can always view subscriptions
        if (str_ends_with($user->email, '@garante.admin')) {
            return true;
        }

        // Business owner and members can view subscriptions
        return $business->members()
            ->where('profile_id', $user->profile->id)
            ->exists();
    }
}
