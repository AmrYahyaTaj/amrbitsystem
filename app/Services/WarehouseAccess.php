<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\HandoverRequest;
use App\Models\ProductionOrder;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Support\CompanyContext;

class WarehouseAccess
{
    public function managed(Warehouse $w, ?int $user = null): bool
    {
        if (! $w->is_active || $w->type === 'vehicle') {
            return false;
        }
        $id = $user ?? auth()->id();
        $role = app(CompanyContext::class)->company->users()->where('users.id', $id)->wherePivot('is_active', true)->value('company_user.role');
        if (in_array($role, ['manager', 'supervisor'], true)) {
            return true;
        }

        return (int) $w->custodian_id === $id && $role === ($w->type === 'production' ? 'production' : 'warehouse_keeper');
    }

    public function recipient(Warehouse $w, int $id): void
    {
        abort_unless($w->is_active, 422, 'الموقع موقوف.');
        if ($w->type === 'vehicle') {
            return;
        }
        abort_if($w->custodian_id && (int) $w->custodian_id !== $id, 422, 'اختر مسؤول الموقع المحدد لاستلام عهدته.');
        abort_unless($this->managed($w, $id), 422, 'المستلم ليس مسؤولًا فعالًا عن الموقع.');
    }

    public function visible()
    {
        $query = Warehouse::query();
        $role = app(CompanyContext::class)->role;
        if ($role->managesOperations() || $role === CompanyRole::Viewer) {
            return $query;
        }
        if (in_array($role, [CompanyRole::WarehouseKeeper, CompanyRole::Production], true)) {
            return $query->where('custodian_id', auth()->id())->where('type', $role === CompanyRole::Production ? 'production' : 'warehouse');
        }
        $ids = Warehouse::where('type', 'vehicle')->get()->filter(fn ($w) => app(HandoverWorkflow::class)->stockOwner($w) === auth()->id())->pluck('id');

        return $query->whereIn('id', $ids);
    }

    public function save(array $d): Warehouse
    {
        abort_unless(app(CompanyContext::class)->role->managesOperations(), 403);
        $w = isset($d['id']) ? Warehouse::lockForUpdate()->findOrFail($d['id']) : new Warehouse;
        abort_if($w->exists && $w->type === 'vehicle', 422, 'مخزن المركبة يُدار من قسم الأسطول.');
        $role = $d['type'] === 'production' ? 'production' : 'warehouse_keeper';
        app(HandoverWorkflow::class)->member($d['custodian_id'], [$role]);
        if ($w->exists && ($w->type !== $d['type'] || (int) $w->custodian_id !== (int) $d['custodian_id'] || ! $d['is_active'])) {
            abort_if(HandoverRequest::where('status', 'pending')->where(fn ($q) => $q->where('from_warehouse_id', $w->id)->orWhere('to_warehouse_id', $w->id))->exists(), 422, 'عالج طلبات الموقع المعلقة قبل تغيير مسؤوله أو نوعه أو إيقافه.');
            abort_if(ProductionOrder::where('warehouse_id', $w->id)->whereIn('status', ['planned', 'in_progress'])->exists(), 422, 'عالج أوامر الإنتاج المفتوحة أولًا.');
            // Initial linking is allowed; reassignment of physical stock is never silent.
            abort_if(($w->custodian_id || $w->type !== $d['type'] || ! $d['is_active']) && StockBalance::where('warehouse_id', $w->id)->sum('quantity') > 0, 422, 'انقل العهدة بموافقة الطرف الآخر وصفر الرصيد قبل تغيير المسؤول أو نوع الموقع أو إيقافه.');
        }
        if ($w->exists && ! empty($d['capacity_units'])) {
            $physical = (int) StockBalance::where('warehouse_id', $w->id)->sum('quantity');
            $incoming = (int) HandoverRequest::where('status', 'pending')->where('to_warehouse_id', $w->id)->sum('reserved_units');
            abort_if($physical + $incoming > $d['capacity_units'], 422, 'السعة المقترحة أقل من الرصيد والطلبات الواردة المعلقة.');
        }
        $w->fill($d);
        $w->save();
        app(AuditRecorder::class)->record('warehouse.configured', $w, ['custodian_id' => $w->custodian_id, 'reason' => $d['notes'] ?? null]);

        return $w;
    }
}
