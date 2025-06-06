<?php

namespace App\Policies;

use App\Models\Business;
use App\Models\User;

class BusinessAccountPolicy
{
    /**
     * Create a new policy instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * Determine if the user can view business accounts.
     */
    public function viewAccounts(User $user, Business $business): bool
    {
        return $business->members()->where('profile_id', $user->profile->id)->exists();
    }

    /**
     * Determine if the user can manage business accounts.
     */
    public function manageAccounts(User $user, Business $business): bool
    {
        return $business->owner_id === $user->id;
    }
}
