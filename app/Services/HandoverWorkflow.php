<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\CashHandover;
use App\Models\Driver;
use App\Models\HandoverRequest;
use App\Models\Product;
use App\Models\RepresentativeVehicleAssignment;
use App\Models\StockBalance;
use App\Models\Trip;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Warehouse;
use App\Models\WorkspaceNotification;
use App\Support\CompanyContext;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class HandoverWorkflow
{
    public function recipients()
    {
        return app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->wherePivot('role', '!=', 'viewer')->get(['users.id', 'name', 'username'])->map(fn ($u) => ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->pivot->role])->values();
    }

    public function visible(): Builder
    {
        $query = HandoverRequest::query();
        if (! app(CompanyContext::class)->role->managesOperations()) {
            $query->where(function ($q) {
                $q->where('sender_id', auth()->id())->orWhere('recipient_id', auth()->id())->orWhere('created_by', auth()->id());
                if (app(CompanyContext::class)->role === CompanyRole::Accountant) {
                    $q->orWhere(fn ($cash) => $cash->where('kind', 'cash')->whereNull('recipient_id'));
                }
            });
        }

        return $query;
    }

    public function find(int $id, bool $lock = false): HandoverRequest
    {
        return $this->visible()->when($lock, fn ($q) => $q->lockForUpdate())->findOrFail($id);
    }

    public function member(int $id, ?array $roles = null)
    {
        $user = app(CompanyContext::class)->company->users()->where('users.id', $id)->wherePivot('is_active', true)->lockForUpdate()->first();
        abort_unless($user && ($roles === null || in_array($user->pivot->role, $roles, true)), 422, 'اختر حسابًا فعالًا في الشركة بالدور المناسب.');

        return $user;
    }

    private function person(int $id): array
    {
        $u = app(CompanyContext::class)->company->users()->where('users.id', $id)->firstOrFail();

        return ['id' => $u->id, 'name' => $u->name, 'username' => $u->username, 'role' => $u->pivot->role];
    }

    private function make(string $kind, int $sender, ?int $recipient, string $note, array $payload, array $fields = []): HandoverRequest
    {
        abort_if($recipient !== null && ($recipient === $sender || $recipient === auth()->id()), 422, 'لا يمكن اعتماد استلامك بنفسك؛ اختر الطرف الآخر.');
        if ($recipient !== null) {
            $this->member($recipient, ['manager', 'supervisor', 'representative', 'accountant', 'driver', 'warehouse_keeper', 'production', 'quality_inspector', 'quality_manager']);
        }
        $snapshot = [
            'company' => app(CompanyContext::class)->company->only(['id', 'name', 'currency', 'timezone']),
            'sender' => $this->person($sender), 'recipient' => $recipient ? $this->person($recipient) : null,
            'created_by' => $this->person(auth()->id()), 'details' => $payload,
        ];
        $h = HandoverRequest::create($fields + ['code' => 'HO-'.Str::upper(Str::random(12)), 'kind' => $kind, 'sender_id' => $sender, 'recipient_id' => $recipient, 'created_by' => auth()->id(), 'sender_note' => $note, 'snapshot' => $snapshot]);
        app(AuditRecorder::class)->record('handover.requested', $h, ['kind' => $kind, 'recipient_id' => $recipient]);

        return $h;
    }

    public function notify(HandoverRequest $h, array $users, string $event, string $title, string $body): void
    {
        $active = app(CompanyContext::class)->company->users()->wherePivot('is_active', true)->whereIn('users.id', array_unique(array_filter($users)))->pluck('users.id');
        foreach ($active as $userId) {
            if ((int) $userId !== auth()->id()) {
                WorkspaceNotification::create(['user_id' => $userId, 'handover_id' => $h->id, 'event' => $event, 'title' => $title, 'body' => $body, 'created_at' => now()]);
            }
        }
    }

    public function participants(HandoverRequest $h): array
    {
        $ids = [$h->sender_id, $h->recipient_id, $h->created_by];
        if ($h->kind === 'cash' && ! $h->recipient_id) {
            $ids = array_merge($ids, app(CompanyContext::class)->company->users()->wherePivotIn('role', ['manager', 'accountant'])->wherePivot('is_active', true)->pluck('users.id')->all());
        }

        return $ids;
    }

    private function pendingResource(string $key): void
    {
        abort_if(DB::table('handover_locks')->where('company_id', app(CompanyContext::class)->id())->where('resource_key', $key)->exists(), 422, 'يوجد طلب تسليم أو إرجاع معلق لهذا المورد. عالجه أولًا من مركز الاستلام.');
    }

    private function hold(HandoverRequest $h, array $keys): void
    {
        foreach ($keys as $key) {
            $this->pendingResource($key);
            DB::table('handover_locks')->insert(['company_id' => $h->company_id, 'handover_id' => $h->id, 'resource_key' => $key]);
        }
    }

    private function assignmentResources(int $vehicleId, int $driverId): array
    {
        $vehicle = Vehicle::lockForUpdate()->findOrFail($vehicleId);
        $driver = Driver::lockForUpdate()->findOrFail($driverId);
        abort_unless($vehicle->status === 'available' && $driver->status === 'active', 422, 'المركبة أو السائق غير متاح.');
        $today = now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString();
        abort_if(! $driver->license_number || ! $driver->license_expires_on || $driver->license_expires_on->toDateString() < $today, 422, 'أكمل رخصة السائق وتأكد من سريانها قبل التسليم.');
        abort_if(Trip::where('vehicle_id', $vehicleId)->where('status', 'active')->exists(), 422, 'المركبة في رحلة نشطة.');
        abort_if(VehicleAssignment::whereNull('returned_at')->where(fn ($q) => $q->where('vehicle_id', $vehicleId)->orWhere('driver_id', $driverId))->exists(), 422, 'المركبة أو السائق لديه استلام قائم. سجّل الإرجاع أولًا.');
        abort_unless($driver->user_id, 422, 'اربط ملف السائق بحساب دخوله من تعديل السائق قبل إرسال طلب الاستلام.');
        $this->member($driver->user_id, ['driver', 'representative']);

        return [$vehicle, $driver];
    }

    public function vehicle(Request $request): HandoverRequest
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations(), 403);
        $d = $request->validate(['vehicle_id' => 'required|integer', 'driver_id' => 'required|integer', 'note' => 'required|string|min:3|max:2000']);

        return DB::transaction(function () use ($d) {
            [$vehicle, $driver] = $this->assignmentResources($d['vehicle_id'], $d['driver_id']);
            $h = $this->make('vehicle_delivery', auth()->id(), $driver->user_id, $d['note'], ['vehicle' => $vehicle->only(['id', 'name', 'plate', 'vehicle_type', 'make', 'model', 'vin', 'odometer_km', 'notes', 'registration_expires_on', 'insurance_expires_on']), 'driver' => $driver->only(['id', 'name', 'employee_code', 'license_number'])], ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id]);
            $this->hold($h, ['vehicle:'.$vehicle->id, 'driver:'.$driver->id]);
            $this->notify($h, [$h->recipient_id], 'requested', 'طلب استلام مركبة بانتظار موافقتك', $vehicle->name.' • '.$vehicle->plate.' • '.$h->code);

            return $h;
        });
    }

    public function vehicleReturn(Request $request): HandoverRequest
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations(), 403);
        $d = $request->validate(['id' => 'required|integer', 'note' => 'required|string|min:3|max:2000']);

        return DB::transaction(function () use ($d) {
            $assignment = VehicleAssignment::lockForUpdate()->findOrFail($d['id']);
            $vehicle = Vehicle::lockForUpdate()->findOrFail($assignment->vehicle_id);
            $driver = Driver::lockForUpdate()->findOrFail($assignment->driver_id);
            abort_if($assignment->returned_at, 422, 'تم إرجاع المركبة بالفعل.');
            abort_if(Trip::where('vehicle_id', $vehicle->id)->where('status', 'active')->exists(), 422, 'عالج الرحلة النشطة قبل إرجاع المركبة.');
            abort_unless($driver->user_id, 422, 'اربط السائق بحساب دخوله لإقرار الإرجاع.');
            $this->member($driver->user_id, ['driver', 'representative']);
            $h = $this->make('vehicle_return', auth()->id(), $driver->user_id, $d['note'], ['vehicle' => $vehicle->only(['id', 'name', 'plate', 'vehicle_type', 'vin', 'odometer_km', 'notes']), 'driver' => $driver->only(['id', 'name', 'employee_code']), 'assigned_at' => $assignment->assigned_at?->toIso8601String()], ['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'assignment_id' => $assignment->id]);
            $this->hold($h, ['vehicle:'.$vehicle->id, 'driver:'.$driver->id]);
            $this->notify($h, [$h->recipient_id], 'requested', 'طلب إقرار إرجاع مركبة', $vehicle->name.' • '.$h->code);

            return $h;
        });
    }

    private function stockRecipient(Warehouse $to, int $recipientId): void
    {
        $this->member($recipientId, $to->type === 'vehicle' ? ['representative', 'driver'] : ($to->type === 'production' ? ['production', 'manager', 'supervisor'] : ['warehouse_keeper', 'manager', 'supervisor']));
        app(WarehouseAccess::class)->recipient($to, $recipientId);
        if ($to->type === 'vehicle') {
            $owner = RepresentativeVehicleAssignment::where('vehicle_id', $to->vehicle_id)->whereNull('returned_at')->value('representative_id');
            abort_if($owner && (int) $owner !== $recipientId, 422, 'مستلم الحمولة يجب أن يطابق المندوب المخصص للمركبة.');
            $active = Trip::where('vehicle_id', $to->vehicle_id)->where('status', 'active')->value('representative_id');
            abort_if($active && (int) $active !== $recipientId, 422, 'الحمولة مرتبطة بعهدة رحلة نشطة لمندوب آخر.');
            if (StockBalance::where('warehouse_id', $to->id)->sum('quantity') > 0) {
                $custodian = HandoverRequest::where('to_warehouse_id', $to->id)->where('kind', 'stock_transfer')->where('status', 'accepted')->latest('id')->value('recipient_id');
                abort_if($custodian && (int) $custodian !== $recipientId, 422, 'العهدة تحتوي بضاعة أقر بها مستلم آخر؛ سوّها أو أرجعها قبل تغيير صاحب الحمولة.');
            }
            abort_if(HandoverRequest::where('kind', 'stock_transfer')->where('status', 'pending')->where('to_warehouse_id', $to->id)->where('recipient_id', '!=', $recipientId)->exists(), 422, 'توجد حمولة معلقة لنفس العهدة باسم مستلم آخر.');
            abort_unless($to->vehicle->status === 'available', 422, 'المركبة غير متاحة لتحميل عهدة جديدة.');
        }
    }

    public function stockOwner(Warehouse $warehouse): ?int
    {
        if ($warehouse->type !== 'vehicle') {
            return null;
        }
        $acknowledged = HandoverRequest::where('to_warehouse_id', $warehouse->id)->where('kind', 'stock_transfer')->where('status', 'accepted')->latest('id')->value('recipient_id');
        if ($acknowledged && StockBalance::where('warehouse_id', $warehouse->id)->sum('quantity') > 0) {
            return (int) $acknowledged;
        }
        $owner = RepresentativeVehicleAssignment::where('vehicle_id', $warehouse->vehicle_id)->whereNull('returned_at')->value('representative_id')
            ?? Trip::where('vehicle_id', $warehouse->vehicle_id)->where('status', 'active')->value('representative_id')
            ?? $acknowledged;
        if (! $owner) {
            $driver = VehicleAssignment::where('vehicle_id', $warehouse->vehicle_id)->whereNull('returned_at')->value('driver_id');
            $owner = $driver ? Driver::whereKey($driver)->value('user_id') : null;
        }

        return $owner ? (int) $owner : null;
    }

    private function capacity(Warehouse $to, int $units, ?int $except = null): void
    {
        if ($to->type === 'vehicle' || $to->capacity_units) {
            $incoming = HandoverRequest::where('kind', 'stock_transfer')->where('status', 'pending')->where('to_warehouse_id', $to->id)->when($except, fn ($q) => $q->whereKeyNot($except))->sum('reserved_units');
            abort_if(StockBalance::where('warehouse_id', $to->id)->sum('quantity') + $incoming + $units > ($to->type === 'vehicle' ? $to->vehicle->capacity_units : $to->capacity_units), 422, 'الحمولة والطلبات المعلقة تتجاوز سعة المركبة.');
        }
    }

    public function stock(Request $request): HandoverRequest
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations() || in_array(app(CompanyContext::class)->role, [CompanyRole::WarehouseKeeper, CompanyRole::Production, CompanyRole::Representative, CompanyRole::Driver], true), 403);
        if (! $request->has('items') && $request->has('product_id')) {
            $request->merge(['items' => [['product_id' => $request->input('product_id'), 'quantity' => $request->input('quantity')]]]);
        }
        $d = $request->validate(['from_id' => 'required|integer', 'to_id' => 'required|integer|different:from_id', 'recipient_id' => 'required|integer', 'note' => 'required|string|min:3|max:2000', 'items' => 'required|array|list|min:1|max:50', 'items.*.product_id' => 'required|integer|distinct', 'items.*.quantity' => 'required|integer|between:1,100000']);

        return DB::transaction(function () use ($d) {
            $locations = Warehouse::whereIn('id', [$d['from_id'], $d['to_id']])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            abort_unless($locations->count() === 2, 422, 'المستودع أو العهدة خارج الشركة الحالية.');
            $from = $locations[$d['from_id']];
            $to = $locations[$d['to_id']];
            abort_unless($from->is_active && $to->is_active, 422, 'المصدر أو الوجهة موقوفة.');
            if ($from->type === 'vehicle') {
                abort_unless($this->stockOwner($from) === auth()->id(), 403, 'إرجاع بضاعة عهدة المركبة يجب أن يطلبه صاحب العهدة من حسابه.');
            } else {
                abort_unless(app(WarehouseAccess::class)->managed($from), 403, 'المصدر ليس ضمن عهدتك.');
            }
            $this->stockRecipient($to, $d['recipient_id']);
            $units = array_sum(array_column($d['items'], 'quantity'));
            $this->capacity($to, $units);
            $lines = [];
            foreach ($d['items'] as $line) {
                $product = Product::where('is_active', true)->findOrFail($line['product_id']);
                StockBalance::where('warehouse_id', $from->id)->where('product_id', $product->id)->lockForUpdate()->first();
                if (app(StockLedger::class)->available($from->id, $product->id) < $line['quantity']) {
                    abort(422, app(StockLedger::class)->shortageMessage($from, $product, $line['quantity']));
                }
                $lines[] = ['product_id' => $product->id, 'name' => $product->name, 'sku' => $product->sku, 'unit' => $product->unit, 'quantity' => $line['quantity'], 'lots' => app(LotLedger::class)->allocate($from, $product, $line['quantity'])];
            }
            $h = $this->make('stock_transfer', auth()->id(), $d['recipient_id'], $d['note'], ['from' => $from->only(['id', 'name', 'type', 'vehicle_id']), 'to' => $to->only(['id', 'name', 'type', 'vehicle_id']), 'items' => $lines], ['from_warehouse_id' => $from->id, 'to_warehouse_id' => $to->id, 'reserved_units' => $units]);
            foreach ($lines as $line) {
                DB::table('handover_stock_reservations')->insert(['company_id' => $h->company_id, 'handover_id' => $h->id, 'warehouse_id' => $from->id, 'product_id' => $line['product_id'], 'quantity' => $line['quantity']]);
            }
            foreach ($lines as $line) {
                foreach ($line['lots'] as $lot) {
                    DB::table('handover_lot_reservations')->insert(['company_id' => $h->company_id, 'handover_id' => $h->id, 'warehouse_id' => $from->id, 'lot_id' => $lot['lot_id'], 'quantity' => $lot['quantity']]);
                }
            }
            $this->notify($h, [$h->recipient_id], 'requested', 'حمولة بانتظار فحصك وإقرار الاستلام', count($lines).' أصناف • '.$units.' وحدة • '.$h->code);

            return $h;
        });
    }

    public function asset(Request $request): HandoverRequest
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations() || app(CompanyContext::class)->role === CompanyRole::WarehouseKeeper, 403);
        $d = $request->validate(['recipient_id' => 'required|integer', 'note' => 'required|string|min:3|max:2000', 'items' => 'required|array|list|min:1|max:50', 'items.*.name' => 'required|string|max:150', 'items.*.serial' => 'nullable|string|max:100', 'items.*.quantity' => 'required|integer|between:1,100000', 'items.*.condition' => 'required|string|min:3|max:500']);

        return DB::transaction(function () use ($d) {
            $h = $this->make('asset', auth()->id(), $d['recipient_id'], $d['note'], ['items' => $d['items']]);
            $this->notify($h, [$h->recipient_id], 'requested', 'طلب استلام عهدة ومعدات', $h->code.' • راجع العناصر وحالتها قبل الموافقة.');

            return $h;
        });
    }

    public function registerCash(CashHandover $cash): HandoverRequest
    {
        $existing = HandoverRequest::where('cash_handover_id', $cash->id)->first();
        if ($existing) {
            return $existing;
        }
        $h = $this->make('cash', $cash->collector_id, null, $cash->note ?: $cash->reference, ['amount_minor' => $cash->amount_minor, 'reference' => $cash->reference, 'cash_code' => $cash->code], ['cash_handover_id' => $cash->id, 'created_by' => $cash->requested_by]);
        $recipients = array_diff($this->participants($h), [$cash->collector_id, $cash->requested_by]);
        $this->notify($h, $recipients, 'requested', 'طلب تسليم نقد بانتظار الاستلام', $cash->code.' • '.$cash->reference);

        return $h;
    }

    public function eligibleRecipient(HandoverRequest $h): bool
    {
        if (in_array(auth()->id(), [$h->sender_id, $h->created_by], true)) {
            return false;
        }
        if ($h->kind === 'cash') {
            return in_array(app(CompanyContext::class)->role, [CompanyRole::Manager, CompanyRole::Accountant], true);
        }

        $roles = match ($h->kind) {
            'vehicle_delivery', 'vehicle_return' => [CompanyRole::Driver, CompanyRole::Representative],
            'stock_transfer' => ($h->snapshot['details']['to']['type'] ?? null) === 'vehicle'
                ? [CompanyRole::Representative, CompanyRole::Driver]
                : (($h->snapshot['details']['to']['type'] ?? null) === 'production' ? [CompanyRole::Production, CompanyRole::Manager, CompanyRole::Supervisor] : [CompanyRole::WarehouseKeeper, CompanyRole::Manager, CompanyRole::Supervisor]),
            default => [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Representative, CompanyRole::Accountant, CompanyRole::Driver, CompanyRole::WarehouseKeeper, CompanyRole::Production, CompanyRole::QualityInspector, CompanyRole::QualityManager],
        };

        return (int) $h->recipient_id === auth()->id() && in_array(app(CompanyContext::class)->role, $roles, true);
    }

    public function canRespond(HandoverRequest $h): bool
    {
        return $h->status === 'pending' && $this->eligibleRecipient($h);
    }

    private function free(HandoverRequest $h): void
    {
        DB::table('handover_lot_reservations')->where('company_id', $h->company_id)->where('handover_id', $h->id)->whereNull('released_at')->update(['released_at' => now()]);
        DB::table('handover_locks')->where('company_id', $h->company_id)->where('handover_id', $h->id)->delete();
        DB::table('handover_stock_reservations')->where('company_id', $h->company_id)->where('handover_id', $h->id)->whereNull('released_at')->update(['released_at' => now()]);
    }

    private function decision(HandoverRequest $h, string $status, string $note, array $result = []): void
    {
        $h->status = $status;
        $h->decided_by = auth()->id();
        $h->decided_at = now();
        $h->response_note = $note;
        if ($status === 'accepted') {
            $receipt = ['schema' => 1, 'code' => $h->code, 'kind' => $h->kind, 'requested_at' => $h->created_at->toIso8601String(), 'snapshot' => $h->snapshot, 'sender_note' => $h->sender_note, 'accepted_at' => $h->decided_at->toIso8601String(), 'accepted_by' => $this->person(auth()->id()), 'response_note' => $note, 'result' => $result];
            $h->receipt = $receipt;
            $h->receipt_hash = hash('sha256', json_encode($receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        }
        $h->save();
        $this->free($h);
        $this->notify($h, $this->participants($h), $status, ['accepted' => 'تم إقرار الاستلام وإصدار المحضر', 'rejected' => 'رُفض طلب الاستلام', 'cancelled' => 'أُلغي طلب التسليم'][$status], $h->code.' • '.$note);
        app(AuditRecorder::class)->record('handover.'.$status, $h, ['note' => $note, 'receipt_hash' => $h->receipt_hash]);
    }

    public function cashDecision(CashHandover $cash, string $status, string $note): void
    {
        $h = HandoverRequest::where('cash_handover_id', $cash->id)->lockForUpdate()->first();
        if (! $h || $h->status !== 'pending') {
            return;
        }
        if ($status === 'accepted') {
            $h->recipient_id = auth()->id();
        }
        $this->decision($h, $status, $note, ['cash_handover_id' => $cash->id]);
    }

    public function respond(int $id, string $decision, string $note): HandoverRequest
    {
        return DB::transaction(function () use ($id, $decision, $note) {
            $h = $this->find($id, true);
            abort_unless($h->status === 'pending', 409, 'عولج هذا الطلب بالفعل؛ لن يتكرر الاستلام.');
            abort_unless($this->canRespond($h), 403, 'القرار متاح للمستلم المحدد فقط، ولا يجوز اعتماد الطلب من مقدمه.');
            abort_unless(in_array($decision, ['accepted', 'rejected'], true), 422);
            if ($h->kind === 'cash') {
                app(CashCustody::class)->review(CashHandover::findOrFail($h->cash_handover_id), $decision === 'accepted', $note);

                return $h->fresh();
            }
            $result = [];
            if ($decision === 'accepted') {
                $this->member($h->sender_id);
                if (in_array($h->kind, ['vehicle_delivery', 'vehicle_return'], true)) {
                    $this->member($h->sender_id, ['manager', 'supervisor']);
                } elseif ($h->kind === 'asset') {
                    $this->member($h->sender_id, ['manager', 'supervisor', 'warehouse_keeper']);
                }
                if ($h->kind === 'vehicle_delivery') {
                    [$vehicle, $driver] = $this->assignmentResources($h->vehicle_id, $h->driver_id);
                    abort_unless((int) $driver->user_id === auth()->id(), 409, 'حساب السائق المرتبط تغير؛ أعد إصدار الطلب.');
                    $assignment = VehicleAssignment::create(['vehicle_id' => $vehicle->id, 'driver_id' => $driver->id, 'active_vehicle_id' => $vehicle->id, 'active_driver_id' => $driver->id, 'assigned_by' => $h->sender_id, 'assigned_at' => now(), 'handover_note' => $h->sender_note]);
                    $h->assignment_id = $assignment->id;
                    $result = ['vehicle_assignment_id' => $assignment->id];
                    app(AuditRecorder::class)->record('vehicle.assigned', $assignment, ['handover_id' => $h->id, 'acknowledged_by' => auth()->id()]);
                } elseif ($h->kind === 'vehicle_return') {
                    Vehicle::lockForUpdate()->findOrFail($h->vehicle_id);
                    $driver = Driver::lockForUpdate()->findOrFail($h->driver_id);
                    abort_unless((int) $driver->user_id === auth()->id(), 409, 'حساب السائق تغير.');
                    $assignment = VehicleAssignment::lockForUpdate()->findOrFail($h->assignment_id);
                    abort_if($assignment->returned_at || Trip::where('vehicle_id', $h->vehicle_id)->where('status', 'active')->exists(), 422, 'لا يمكن إرجاع المركبة الآن؛ راجع عهدتها ورحلتها.');
                    $assignment->update(['returned_at' => now(), 'returned_by' => $h->sender_id, 'return_note' => $h->sender_note, 'active_vehicle_id' => null, 'active_driver_id' => null]);
                    $result = ['vehicle_assignment_id' => $assignment->id];
                    app(AuditRecorder::class)->record('vehicle.returned', $assignment, ['handover_id' => $h->id, 'acknowledged_by' => auth()->id()]);
                } elseif ($h->kind === 'stock_transfer') {
                    $stores = Warehouse::whereIn('id', [$h->from_warehouse_id, $h->to_warehouse_id])->orderBy('id')->lockForUpdate()->get()->keyBy('id');
                    $from = $stores[$h->from_warehouse_id];
                    $to = $stores[$h->to_warehouse_id];
                    $this->member($h->sender_id, $from->type === 'vehicle' ? ['representative', 'driver'] : ['manager', 'supervisor', 'warehouse_keeper', 'production']);
                    abort_if($from->type === 'vehicle' && $this->stockOwner($from) !== (int) $h->sender_id, 409, 'مسؤول العهدة المصدر تغير؛ أعد إصدار الطلب الصحيح.');
                    abort_unless($from->is_active && $to->is_active, 422, 'موقع المصدر أو الوجهة موقوف.');
                    if ($from->type !== 'vehicle') {
                        abort_unless(app(WarehouseAccess::class)->managed($from, $h->sender_id), 409, 'مسؤول المصدر تغير؛ أعد إصدار الطلب.');
                    }
                    $this->stockRecipient($to, auth()->id());
                    $this->capacity($to, $h->reserved_units, $h->id);
                    foreach ($h->snapshot['details']['items'] as $line) {
                        $product = Product::where('is_active', true)->findOrFail($line['product_id']);
                        $lots = app(StockLedger::class)->move($from, $product, -$line['quantity'], 'transfer_out', $h->code, $h->id, $line['lots'] ?? null);
                        app(StockLedger::class)->move($to, $product, $line['quantity'], 'transfer_in', $h->code, null, $lots);
                    }
                    $result = ['reference' => $h->code];
                    app(AuditRecorder::class)->record('stock.transferred', $h, ['reference' => $h->code, 'from_id' => $from->id, 'to_id' => $to->id]);
                }
            }
            $this->decision($h, $decision, $note, $result);

            return $h;
        });
    }

    public function cancel(int $id, string $note): HandoverRequest
    {
        return DB::transaction(function () use ($id, $note) {
            $h = $this->find($id, true);
            abort_unless($h->status === 'pending', 409, 'يمكن إلغاء الطلب المعلق فقط.');
            abort_unless(in_array(auth()->id(), [$h->sender_id, $h->created_by], true) || app(CompanyContext::class)->role->managesOperations(), 403);
            if ($h->kind === 'cash') {
                app(CashCustody::class)->cancel(CashHandover::findOrFail($h->cash_handover_id), $note);

                return $h->fresh();
            }
            $this->decision($h, 'cancelled', $note);

            return $h;
        });
    }
}
