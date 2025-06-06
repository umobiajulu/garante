<?php

namespace App\Policies;

use App\Models\SubscriptionAccount;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionAccountPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        // Admin users can do everything
        if (str_ends_with($user->email, '@garante.admin')) {
            return true;
        }
    }

    public function view(User $user, SubscriptionAccount $account)
    {
        // Users can view subscription accounts of businesses they are members of
        return $account->business->members()
            ->where('profile_id', $user->profile->id)
            ->exists();
    }

    public function create(User $user, SubscriptionAccount $account)
    {
        // Only admin users can create subscription accounts
        return false;
    }

    public function update(User $user, SubscriptionAccount $account)
    {
        // Only admin users can update subscription accounts
        return false;
    }

    public function delete(User $user, SubscriptionAccount $account)
    {
        // Only admin users can delete subscription accounts
        return false;
    }
} 