<?php

namespace App\Policies;

use App\Models\Account;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AccountPolicy
{
    use HandlesAuthorization;

    public function before(User $user, $ability)
    {
        // Admin users can do everything
        if (str_ends_with($user->email, '@garante.admin')) {
            return true;
        }
    }

    public function view(User $user, Account $account)
    {
        // Users can view accounts of businesses they are members of
        return $account->business->members()
            ->where('profile_id', $user->profile->id)
            ->exists();
    }

    public function create(User $user, Account $account)
    {
        // Only business owners can create accounts
        return $account->business->owner_id === $user->id;
    }

    public function update(User $user, Account $account)
    {
        // Only business owners can update accounts
        return $account->business->owner_id === $user->id;
    }

    public function delete(User $user, Account $account)
    {
        // Only business owners can delete accounts
        return $account->business->owner_id === $user->id;
    }
} 