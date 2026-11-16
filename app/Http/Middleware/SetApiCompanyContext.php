<?php

namespace App\Http\Middleware;

use App\Enums\CompanyRole;
use App\Support\CompanyContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetApiCompanyContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $context = app(CompanyContext::class);
        $context->clear();
        $id = $request->header('X-Company-ID');
        abort_unless(is_string($id) && ctype_digit($id), 422, 'حدد الشركة في X-Company-ID.');
        $company = $request->user()->companies()->where('companies.id', (int) $id)->where('companies.is_active', true)->wherePivot('is_active', true)->first();
        abort_unless($company, 403);
        $role = CompanyRole::tryFrom($company->pivot->role);
        abort_unless($role, 403);
        $token = $request->user()->currentAccessToken();
        abort_unless($token && $token->can($request->isMethod('GET') ? 'operations:read' : 'operations:write'), 403);
        $context->set($company, $role);
        try {
            return $next($request);
        } finally {
            $context->clear();
        }
    }
}
