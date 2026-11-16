<?php

namespace App\Support;

use App\Enums\CompanyRole;
use App\Models\Company;

class CompanyContext
{
    public ?Company $company = null;

    public ?CompanyRole $role = null;

    public function set(Company $company, CompanyRole $role): void
    {
        $this->company = $company;
        $this->role = $role;
    }

    public function id(): int
    {
        return $this->company?->id ?? throw new \LogicException('An explicit company context is required.');
    }

    public function clear(): void
    {
        $this->company = null;
        $this->role = null;
    }
}
