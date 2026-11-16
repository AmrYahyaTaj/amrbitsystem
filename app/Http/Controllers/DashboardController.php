<?php

namespace App\Http\Controllers;

use App\Enums\VisitStatus;
use App\Models\AuditLog;
use App\Models\Customer;
use App\Models\Vehicle;
use App\Models\Visit;
use App\Support\CompanyContext;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __invoke(Request $request, CompanyContext $context)
    {
        $canSeeAll = $context->role->seesOperations();
        $visits = Visit::query();
        if (! $canSeeAll) {
            $visits->where('representative_id', $request->user()->id);
        }
        $today = now()->timezone($context->company->timezone)->toDateString();
        $todayVisits = (clone $visits)->whereDate('scheduled_for', $today);

        return view('dashboard', [
            'stats' => [
                'customers' => $canSeeAll ? Customer::count() : null,
                'vehicles' => $canSeeAll ? Vehicle::count() : null,
                'today' => (clone $todayVisits)->count(),
                'completed' => (clone $todayVisits)->where('status', VisitStatus::Completed->value)->count(),
            ],
            'visits' => $todayVisits->with(['customer', 'representative'])->orderBy('id')->limit(8)->get(),
            'activities' => $context->role->managesOperations() ? AuditLog::orderByDesc('id')->limit(5)->get() : collect(),
        ]);
    }
}
