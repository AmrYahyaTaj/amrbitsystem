<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\AuditLog;
use App\Models\LocationPoint;
use App\Models\Order;
use App\Models\StockBalance;
use App\Models\Trip;
use App\Services\TripPlanning;
use App\Support\CompanyContext;

class TripFileController extends Controller
{
    public function show(int $id, TripPlanning $planning)
    {
        $role = app(CompanyContext::class)->role;
        abort_unless(in_array($role, [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Representative, CompanyRole::Viewer], true), 403);
        $trip = Trip::with(['representative:id,name', 'vehicle', 'warehouse', 'driver'])->findOrFail($id);
        abort_if($role === CompanyRole::Representative && $trip->representative_id !== auth()->id(), 403);
        $financial = $role !== CompanyRole::Viewer;
        $visits = $trip->visits()->with('customer.area')->orderBy('sequence')->orderBy('id')->get();
        $orders = Order::where('trip_id', $trip->id);
        $events = AuditLog::where('subject_type', 'trips')->where('subject_id', $trip->id)->orderByDesc('id')->get();
        $names = app(CompanyContext::class)->company->users()->whereIn('users.id', $events->pluck('actor_id'))->pluck('name', 'users.id');

        return response()->json([
            'trip' => $trip, 'visits' => $visits,
            'blockers' => $planning->blockers($trip),
            'can_plan' => $role->managesOperations(),
            'stock' => StockBalance::with('product')->where('warehouse_id', $trip->warehouse_id)->get(),
            'stock_definition' => 'رصيد عهدة السيارة الحالي؛ ليس لقطة مخزون هذه الرحلة أو إثبات إقفالها.',
            'last_point' => LocationPoint::where('trip_id', $trip->id)->orderByDesc('captured_at')->first(),
            'orders' => $financial ? (clone $orders)->with('customer:id,company_id,name')->orderByDesc('id')->get() : [],
            'financial' => $financial ? ['sales_minor' => (int) (clone $orders)->where('status', 'fulfilled')->sum('total_minor'), 'paid_minor' => (int) (clone $orders)->where('status', 'fulfilled')->sum('paid_minor'), 'drafts' => (clone $orders)->where('status', 'draft')->count()] : null,
            'events' => $events->map(fn ($event) => ['action' => $event->action, 'at' => $event->created_at, 'actor' => $names[$event->actor_id] ?? 'عضو سابق', 'reason' => $event->metadata['reason'] ?? null, 'before' => $role->managesOperations() ? ($event->metadata['before'] ?? null) : null, 'after' => $role->managesOperations() ? ($event->metadata['after'] ?? null) : null]),
        ]);
    }
}
