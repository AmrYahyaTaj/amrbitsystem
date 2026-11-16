<?php

namespace App\Services;

use App\Models\CashHandover;
use App\Models\Collection;
use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CashCustody
{
    public function __construct(private AuditRecorder $audit) {}

    private function lockCollector(int $id): void
    {
        $member = DB::table('company_user')->where('company_id', app(CompanyContext::class)->id())->where('user_id', $id)->lockForUpdate()->first();
        abort_unless($member, 422, 'الشخص ليس عضوًا في الشركة.');
    }

    public function balances(?int $onlyUser = null): array
    {
        $members = app(CompanyContext::class)->company->users()->when($onlyUser, fn ($q) => $q->where('users.id', $onlyUser))->get(['users.id', 'name']);
        $received = Collection::where('method', 'cash')->selectRaw('actor_id,SUM(amount_minor) AS amount')->groupBy('actor_id')->pluck('amount', 'actor_id');
        $confirmed = CashHandover::where('status', 'accepted')->selectRaw('collector_id,SUM(amount_minor) AS amount')->groupBy('collector_id')->pluck('amount', 'collector_id');
        $pending = CashHandover::where('status', 'pending')->selectRaw('collector_id,SUM(amount_minor) AS amount')->groupBy('collector_id')->pluck('amount', 'collector_id');

        return $members->map(function ($u) use ($received, $confirmed, $pending) {
            $cash = (int) ($received[$u->id] ?? 0);
            $accepted = (int) ($confirmed[$u->id] ?? 0);
            $reserved = (int) ($pending[$u->id] ?? 0);

            return ['id' => $u->id, 'name' => $u->name, 'received_minor' => $cash, 'accepted_minor' => $accepted, 'pending_minor' => $reserved, 'balance_minor' => $cash - $accepted, 'available_minor' => $cash - $accepted - $reserved];
        })->values()->all();
    }

    public function request(int $collectorId, int $amount, string $reference, ?string $note): CashHandover
    {
        $this->lockCollector($collectorId);
        $received = (int) Collection::where('actor_id', $collectorId)->where('method', 'cash')->sum('amount_minor');
        $allocated = (int) CashHandover::where('collector_id', $collectorId)->whereIn('status', ['pending', 'accepted'])->sum('amount_minor');
        if ($amount > $received - $allocated) {
            throw ValidationException::withMessages(['amount_minor' => 'المبلغ يتجاوز النقد المتاح أو توجد طلبات تسليم معلقة.']);
        }
        $h = CashHandover::create(['code' => 'CH-'.Str::upper(Str::random(10)), 'collector_id' => $collectorId, 'requested_by' => auth()->id(), 'amount_minor' => $amount, 'reference' => $reference, 'note' => $note]);
        $this->audit->record('cash.handover_requested', $h, ['collector_id' => $collectorId, 'amount_minor' => $amount]);
        app(HandoverWorkflow::class)->registerCash($h);

        return $h;
    }

    public function review(CashHandover $handover, bool $accept, string $note): void
    {
        $this->lockCollector($handover->collector_id);
        $h = CashHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
        abort_unless($h->status === 'pending', 422, 'الطلب عولج بالفعل.');
        abort_if($h->collector_id === auth()->id() || $h->requested_by === auth()->id(), 403, 'اعتماد تسليم النقد يتطلب مستلمًا آخر.');
        $h->status = $accept ? 'accepted' : 'rejected';
        $h->reviewed_by = auth()->id();
        $h->reviewed_at = now();
        $h->review_note = $note;
        $h->save();
        $this->audit->record($accept ? 'cash.handover_accepted' : 'cash.handover_rejected', $h, ['amount_minor' => $h->amount_minor, 'collector_id' => $h->collector_id]);
        app(HandoverWorkflow::class)->cashDecision($h, $accept ? 'accepted' : 'rejected', $note);
    }

    public function cancel(CashHandover $handover, string $note): void
    {
        $this->lockCollector($handover->collector_id);
        $h = CashHandover::whereKey($handover->id)->lockForUpdate()->firstOrFail();
        abort_unless($h->collector_id === auth()->id() || $h->requested_by === auth()->id(), 403);
        abort_unless($h->status === 'pending', 422, 'الطلب عولج بالفعل.');
        $h->status = 'cancelled';
        $h->reviewed_by = auth()->id();
        $h->reviewed_at = now();
        $h->review_note = $note;
        $h->save();
        $this->audit->record('cash.handover_cancelled',$h,['reason' => $note]);
        app(HandoverWorkflow::class)->cashDecision($h, 'cancelled', $note);
    }
}
