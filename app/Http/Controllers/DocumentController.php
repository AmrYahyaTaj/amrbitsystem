<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\Collection;
use App\Models\Order;
use App\Support\CompanyContext;

class DocumentController extends Controller
{
    public function order(int $id)
    {
        $context = app(CompanyContext::class);
        abort_unless(in_array($context->role, [CompanyRole::Manager, CompanyRole::Supervisor, CompanyRole::Accountant, CompanyRole::Representative], true), 403);
        $order = Order::with(['customer', 'representative', 'warehouse', 'items.product'])->findOrFail($id);
        if ($context->role === CompanyRole::Representative) {
            abort_unless($order->representative_id === auth()->id(), 403);
        }

        return response()->view('documents.order', ['order' => $order, 'company' => $context->company, 'receipts' => Collection::with('actor:id,name')->where('order_id', $order->id)->orderBy('id')->get()]);
    }
}
