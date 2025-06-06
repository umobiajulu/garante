<?php

namespace App\Policies;

use App\Models\Profile;
use App\Models\User;

class ProfilePolicy
{
    public function view(User $user, Profile $profile): bool
    {
        return $user->id === $profile->user_id || $user->isArbitrator();
    }

    public function update(User $user, Profile $profile): bool
    {
        // Only profile owner can update
        if ($user->id !== $profile->user_id) {
            return false;
        }

        // Check for unresolved disputes
        if ($profile->hasUnresolvedDisputes()) {
            return false;
        }

        return true;
    }

    public function verify(User $user, Profile $profile): bool
    {
        return $user->isArbitrator();
    }

    public function delete(User $user, Profile $profile): bool
    {
        return $user->id === $profile->user_id;
    }
} 