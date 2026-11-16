<?php

namespace App\Policies;

use App\Enums\CompanyRole;
use App\Models\User;
use App\Models\Visit;
use App\Support\CompanyContext;

class VisitPolicy
{
    public function view(User $user, Visit $visit): bool
    {
        $context = app(CompanyContext::class);

        return $visit->company_id === $context->id()
            && ($context->role?->seesOperations()
                || ($context->role === CompanyRole::Representative && $visit->representative_id === $user->id));
    }

    public function execute(User $user, Visit $visit): bool
    {
        $context = app(CompanyContext::class);

        // Managers schedule; only the assigned representative can submit presence evidence.
        return $visit->company_id === $context->id()
            && $context->role === CompanyRole::Representative
            && $visit->representative_id === $user->id;
    }
}
