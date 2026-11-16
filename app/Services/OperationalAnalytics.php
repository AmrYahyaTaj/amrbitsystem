<?php

namespace App\Services;

use App\Enums\CompanyRole;
use App\Models\Collection;
use App\Models\Order;
use App\Models\Visit;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class OperationalAnalytics
{
    public function orders(array $filters)
    {
        return Order::query()->when($filters['representative_id'] ?? null, fn ($q, $id) => $q->where('representative_id', $id))->when($filters['area_id'] ?? null, fn ($q, $id) => $q->whereHas('customer', fn ($c) => $c->where('area_id', $id)));
    }

    public function visits(array $filters)
    {
        return Visit::query()->where('status', '!=', 'cancelled')->when($filters['representative_id'] ?? null, fn ($q, $id) => $q->where('representative_id', $id))->when($filters['area_id'] ?? null, fn ($q, $id) => $q->whereHas('customer', fn ($c) => $c->where('area_id', $id)));
    }

    public function build(array $filters): array
    {
        $context = app(CompanyContext::class);
        $tz = $context->company->timezone;
        $start = CarbonImmutable::parse($filters['from'], $tz)->startOfDay();
        $end = CarbonImmutable::parse($filters['to'], $tz)->startOfDay()->addDay();
        $canFinance = in_array($context->role, [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant], true);
        $canVisits = $context->role !== CompanyRole::Accountant;
        $visits = $this->visits($filters)->whereDate('scheduled_for', '>=', $filters['from'])->whereDate('scheduled_for', '<=', $filters['to']);
        $baseOrders = $this->orders($filters)->where('status', 'fulfilled');
        $orders = (clone $baseOrders)->where('created_at', '>=', $start->utc())->where('created_at', '<', $end->utc());
        $receipts = Collection::whereIn('order_id', (clone $baseOrders)->select('orders.id'))->where('collected_at', '>=', $start->utc())->where('collected_at', '<', $end->utc());
        $statuses = $canVisits ? (clone $visits)->selectRaw('status,COUNT(*) as count')->groupBy('status')->pluck('count', 'status')->map(fn ($n) => (int) $n)->all() : [];
        $dailyVisits = $canVisits ? (clone $visits)->selectRaw('DATE(scheduled_for) as day,status,COUNT(*) as count')->groupByRaw('DATE(scheduled_for),status')->toBase()->get() : collect();
        $days = [];
        $expressions = [];
        $bindings = [];
        for ($day = $start; $day < $end; $day = $day->addDay()) {
            $index = count($days);
            $days[] = ['date' => $day->toDateString(), 'sales_minor' => 0, 'visits' => 0, 'completed' => 0];
            $expressions[] = "SUM(CASE WHEN created_at >= ? AND created_at < ? THEN total_minor ELSE 0 END) AS day_$index";
            $bindings[] = $day->utc()->format('Y-m-d H:i:s');
            $bindings[] = $day->addDay()->utc()->format('Y-m-d H:i:s');
        }
        // Bounded conditional aggregates preserve local-day boundaries without driver-specific timezone SQL.
        $sales = $canFinance ? (clone $orders)->selectRaw(implode(',', $expressions), $bindings)->first() : null;
        foreach ($days as $i => &$day) {
            $day['sales_minor'] = $sales ? (int) ($sales->{'day_'.$i} ?? 0) : null;
            $v = $dailyVisits->where('day', $day['date']);
            $day['visits'] = (int) $v->sum('count');
            $day['completed'] = (int) $v->where('status', 'completed')->sum('count');
        }unset($day);
        $today = CarbonImmutable::now($tz)->startOfDay();
        $agingSql = "CASE WHEN due_date IS NULL THEN 'unspecified' WHEN due_date >= ? THEN 'not_due' WHEN due_date >= ? THEN '1_30' WHEN due_date >= ? THEN '31_60' WHEN due_date >= ? THEN '61_90' ELSE 'over_90' END";
        $aging = $canFinance ? (clone $baseOrders)->whereColumn('total_minor', '>', 'paid_minor')->selectRaw("$agingSql as bucket,SUM(total_minor-paid_minor) AS balance_minor", [$today->toDateString(), $today->subDays(30)->toDateString(), $today->subDays(60)->toDateString(), $today->subDays(90)->toDateString()])->groupBy('bucket')->get()->map(fn ($r) => ['bucket' => $r->bucket, 'balance_minor' => (int) $r->balance_minor])->all() : [];

        $salesByRep = $canFinance ? (clone $orders)->selectRaw('representative_id,COUNT(*) as orders_count,SUM(total_minor) as sales_minor,SUM(total_minor-paid_minor) as remaining_minor')->groupBy('representative_id')->get()->keyBy('representative_id') : collect();
        $visitsByRep = $canVisits ? (clone $visits)->selectRaw("representative_id,COUNT(*) as visits_count,SUM(CASE WHEN status='completed' THEN 1 ELSE 0 END) as completed_count,SUM(CASE WHEN status IN ('failed','skipped') THEN 1 ELSE 0 END) as unsuccessful_count")->groupBy('representative_id')->get()->keyBy('representative_id') : collect();
        $collectionsByRep = $canFinance ? DB::table('collections as c')->join('orders as o', 'o.id', '=', 'c.order_id')->where('c.company_id', $context->id())->where('o.company_id', $context->id())->whereIn('o.id', (clone $baseOrders)->select('orders.id'))->where('c.collected_at', '>=', $start->utc())->where('c.collected_at', '<', $end->utc())->selectRaw('o.representative_id,SUM(c.amount_minor) as amount_minor')->groupBy('o.representative_id')->get()->keyBy('representative_id') : collect();
        $ids = $salesByRep->keys()->merge($visitsByRep->keys())->merge($collectionsByRep->keys())->unique();
        $names = $context->company->users()->whereIn('users.id', $ids)->pluck('name', 'users.id');
        $performance = $ids->map(function ($id) use ($salesByRep, $visitsByRep, $collectionsByRep, $names, $canFinance, $canVisits) {
            $s = $salesByRep->get($id);
            $v = $visitsByRep->get($id);

            return ['representative_id' => (int) $id, 'name' => $names[$id] ?? 'عضو سابق', 'visits' => $canVisits ? (int) ($v->visits_count ?? 0) : null, 'completed' => $canVisits ? (int) ($v->completed_count ?? 0) : null, 'unsuccessful' => $canVisits ? (int) ($v->unsuccessful_count ?? 0) : null, 'orders' => $canFinance ? (int) ($s->orders_count ?? 0) : null, 'sales_minor' => $canFinance ? (int) ($s->sales_minor ?? 0) : null, 'collections_minor' => $canFinance ? (int) ($collectionsByRep->get($id)->amount_minor ?? 0) : null, 'remaining_minor' => $canFinance ? (int) ($s->remaining_minor ?? 0) : null];
        })->sortByDesc(fn ($r) => $canFinance ? $r['sales_minor'] : $r['completed'])->values();
        $methods = $canFinance ? (clone $receipts)->selectRaw('method,COUNT(*) as receipts,SUM(amount_minor) as amount_minor')->groupBy('method')->get()->map(fn ($r) => ['method' => $r->method, 'receipts' => (int) $r->receipts, 'amount_minor' => (int) $r->amount_minor])->values() : [];

        return ['filters' => $filters, 'as_of' => now()->toIso8601String(), 'daily' => $days, 'visit_statuses' => $statuses, 'performance' => $performance, 'collection_methods' => $methods, 'summary' => [
            'visits' => array_sum($statuses), 'completed' => $statuses['completed'] ?? 0, 'orders' => $canFinance ? (clone $orders)->count() : null,
            'sales_minor' => $canFinance ? (int) (clone $orders)->sum('total_minor') : null, 'collections_minor' => $canFinance ? (int) (clone $receipts)->sum('amount_minor') : null,
            'remaining_period_orders_minor' => $canFinance ? (int) (clone $orders)->sum(DB::raw('total_minor-paid_minor')) : null,
        ], 'aging' => $aging, 'definitions' => ['sales' => 'الطلبات المسلّمة التي أُنشئت خلال الفترة.', 'collections' => 'إيصالات مستلمة خلال الفترة، بما فيها سداد فواتير أقدم.', 'remaining' => 'المتبقي الحالي على طلبات الفترة.', 'aging' => 'الآجل المفتوح الآن بكل تاريخه، ضمن المندوب والمنطقة المختارين.']];
    }
}
