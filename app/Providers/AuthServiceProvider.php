<?php

namespace App\Providers;

use App\Models\Business;
use App\Models\BusinessInvitation;
use App\Models\Account;
use App\Models\SubscriptionAccount;
use App\Models\Subscription;
use App\Policies\BusinessPolicy;
use App\Policies\BusinessInvitationPolicy;
use App\Policies\AccountPolicy;
use App\Policies\SubscriptionAccountPolicy;
use App\Policies\SubscriptionPolicy;
use Illuminate\Foundation\Support\Providers\AuthServiceProvider as ServiceProvider;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * The model to policy mappings for the application.
     *
     * @var array<class-string, class-string>
     */
    protected $policies = [
        Business::class => BusinessPolicy::class,
        BusinessInvitation::class => BusinessInvitationPolicy::class,
        Account::class => AccountPolicy::class,
        SubscriptionAccount::class => SubscriptionAccountPolicy::class,
        Subscription::class => SubscriptionPolicy::class,
    ];

    /**
     * Register any authentication / authorization services.
     */
    public function boot(): void
    {
        $this->registerPolicies();
    }
} 