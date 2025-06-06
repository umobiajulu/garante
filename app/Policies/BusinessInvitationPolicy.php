<?php

namespace App\Policies;

use App\Models\BusinessInvitation;
use App\Models\User;

class BusinessInvitationPolicy
{
    public function respond(User $user, BusinessInvitation $invitation): bool
    {
        return $user->profile && $user->profile->id === $invitation->profile_id;
    }
} 