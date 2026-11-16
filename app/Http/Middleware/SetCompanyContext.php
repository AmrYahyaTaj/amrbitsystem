<?php

namespace App\Http\Middleware;

use App\Enums\CompanyRole;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class);
        $context->clear();
        $memberships = $request->user()->companies()->where('companies.is_active', true)->wherePivot('is_active', true)->get();
        $selected = $request->session()->get('company_id');
        $company = $selected ? $memberships->firstWhere('id', (int) $selected) : $memberships->first();
        abort_unless($company, 403, 'لا توجد عضوية فعالة في الشركة المطلوبة.');
        $role = CompanyRole::tryFrom($company->pivot->role);
        abort_unless($role, 403);
        $context->set($company, $role);
        $request->session()->put('company_id', $company->id);
        view()->share(['activeCompany' => $company, 'companyRole' => $role, 'availableCompanies' => $memberships]);
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
