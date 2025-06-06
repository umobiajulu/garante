<?php

namespace App\Policies;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class SubscriptionPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        // Admin users can do everything
        if (str_ends_with($user->email, '@garante.admin')) {
            return true;
        }
    }

    public function view(User $user, Subscription $subscription)
    {
        // Users can view subscriptions of businesses they are members of
        return $subscription->business->members()
            ->where('profile_id', $user->profile->id)
            ->exists();
    }

    public function create(User $user, Subscription $subscription)
    {
        // Only admin users can create subscriptions
        return false;
    }

    public function update(User $user, Subscription $subscription)
    {
        // Only admin users can update subscriptions
        return false;
    }

    public function delete(User $user, Subscription $subscription)
    {
        // Only admin users can delete subscriptions
        return false;
    }
} 