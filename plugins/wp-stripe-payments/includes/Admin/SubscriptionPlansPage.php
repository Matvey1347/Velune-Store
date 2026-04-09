<?php

namespace WPStripePayments\Admin;

use WPStripePayments\Subscriptions\PlanService;

class SubscriptionPlansPage
{
    private PlanService $planService;

    public function __construct(PlanService $planService)
    {
        $this->planService = $planService;
    }

    public function register(): void
    {
        $this->planService->register();
    }
}
