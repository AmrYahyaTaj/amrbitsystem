<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Enums\VisitStatus;
use App\Models\Customer;
use App\Models\Visit;
use App\Services\AuditRecorder;
use App\Services\VisitWorkflow;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class VisitController extends Controller
{
    public function index(Request $request, CompanyContext $context)
    {
        abort_unless($context->role->seesOperations() || $context->role === CompanyRole::Representative, 403);
        $data = $request->validate(['status' => ['nullable', Rule::enum(VisitStatus::class)]]);
        $query = Visit::with(['customer', 'representative'])->orderByDesc('scheduled_for')->orderByDesc('id');
        if ($context->role === CompanyRole::Representative) {
            $query->where('representative_id', $request->user()->id);
        }
        if (! empty($data['status'])) {
            $query->where('status', $data['status']);
        }

        return view('visits.index', [
            'visits' => $query->paginate(15)->withQueryString(),
            'customers' => $context->role->managesOperations() ? Customer::where('is_active', true)->orderBy('name')->get() : collect(),
            'representatives' => $context->role->managesOperations() ? $context->company->users()->wherePivot('role', 'representative')->wherePivot('is_active', true)->get() : collect(),
        ]);
    }

    public function store(Request $request, CompanyContext $context, AuditRecorder $audit)
    {
        Gate::authorize('manage-operations');
        $data = $request->validate([
            'customer_id' => ['required', 'integer', Rule::exists('customers', 'id')->where('company_id', $context->id())->where('is_active', true)],
            'representative_id' => ['required', 'integer', Rule::exists('company_user', 'user_id')->where('company_id', $context->id())->where('role', 'representative')->where('is_active', true)],
            'scheduled_for' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.now()->timezone($context->company->timezone)->toDateString()],
        ]);
        DB::transaction(function () use ($data, $audit) {
            $audit->record('visit.scheduled', Visit::create($data));
        });

        return back()->with('success', 'تمت جدولة الزيارة للمندوب.');
    }

    public function show(Visit $visit)
    {
        Gate::authorize('view', $visit);

        return view('visits.show', ['visit' => $visit->load(['customer', 'representative'])]);
    }

    public function checkIn(Request $request, Visit $visit, VisitWorkflow $workflow)
    {
        Gate::authorize('execute', $visit);
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'], 'longitude' => ['required', 'numeric', 'between:-180,180'],
            'accuracy_m' => ['required', 'numeric', 'between:0,10000'], 'captured_at' => ['required', 'date'],
        ]);
        $workflow->checkIn($visit, $data);

        return back()->with('success', 'تم بدء الزيارة وقبول دليل القرب من العميل.');
    }

    public function finish(Request $request, Visit $visit, VisitWorkflow $workflow)
    {
        Gate::authorize('execute', $visit);
        $data = $request->validate([
            'status' => ['required', Rule::in(['completed', 'failed', 'skipped'])],
            'outcome_note' => ['nullable', 'required_if:status,failed,skipped', 'string', 'max:1000'],
        ]);
        $workflow->finish($visit, VisitStatus::from($data['status']), $data['outcome_note'] ?? null);

        return back()->with('success', 'تم حفظ نتيجة الزيارة.');
    }
}
