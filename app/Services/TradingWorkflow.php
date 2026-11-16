<?php

namespace App\Services;

use App\Models\Collection;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TradingWorkflow
{
    public function __construct(private StockLedger $stock, private AuditRecorder $audit) {}

    public function create(array $data): Order
    {
        $order = Order::create(['code' => 'SO-'.strtoupper(Str::random(10)), 'customer_id' => $data['customer_id'], 'representative_id' => $data['representative_id'], 'warehouse_id' => $data['warehouse_id'], 'trip_id' => $data['trip_id'] ?? null, 'payment_terms' => $data['payment_terms'], 'due_date' => $data['due_date'] ?? null]);
        $total = 0;
        foreach ($data['items'] as $line) {
            $product = Product::where('is_active', true)->findOrFail($line['product_id']);
            $amount = $product->price_minor * $line['quantity'];
            OrderItem::create(['order_id' => $order->id, 'product_id' => $product->id, 'quantity' => $line['quantity'], 'unit_price_minor' => $product->price_minor, 'total_minor' => $amount]);
            $total += $amount;
        }
        $order->total_minor = $total;
        $order->initial_paid_minor = match ($data['payment_terms']) {
            'cash' => $total,'partial' => (int) $data['initial_paid_minor'],default => 0
        };
        if ($order->initial_paid_minor > $total || ($data['payment_terms'] === 'partial' && $order->initial_paid_minor >= $total)) {
            throw ValidationException::withMessages(['initial_paid_minor' => 'المبلغ الجزئي يجب أن يكون أقل من الإجمالي.']);
        }
        $order->save();
        $this->audit->record('order.created', $order, ['total_minor' => $total]);

        return $order;
    }

    public function fulfill(Order $order): void
    {
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        if ($order->status !== 'draft') {
            throw ValidationException::withMessages(['status' => 'تم تسليم هذا الطلب بالفعل أو لا يقبل التسليم.']);
        }
        $customer = Customer::whereKey($order->customer_id)->lockForUpdate()->firstOrFail();
        $newDebt = $order->total_minor - $order->initial_paid_minor;
        $existingDebt = Order::where('customer_id', $customer->id)->where('status', 'fulfilled')->sum(DB::raw('total_minor-paid_minor'));
        if ($newDebt > 0 && $customer->credit_limit_minor !== null && $newDebt + $existingDebt > $customer->credit_limit_minor) {
            throw ValidationException::withMessages(['credit' => 'الطلب يتجاوز الحد الائتماني للعميل.']);
        }
        foreach ($order->items as $line) {
            $this->stock->move($order->warehouse, $line->product, -$line->quantity, 'sale', $order->code);
        }
        $order->status = 'fulfilled';
        $order->save();
        $this->audit->record('order.fulfilled', $order);
        if ($order->initial_paid_minor > 0) {
            $this->collect($order, $order->initial_paid_minor, 'cash');
        }
    }

    public function collect(Order $order, int $amount, string $method): Collection
    {
        $order = Order::whereKey($order->id)->lockForUpdate()->firstOrFail();
        if ($order->status !== 'fulfilled' || $amount > $order->total_minor - $order->paid_minor) {
            throw ValidationException::withMessages(['amount_minor' => 'المبلغ يتجاوز المستحق أو الطلب لم يُسلّم.']);
        }
        $receipt = Collection::create(['order_id' => $order->id, 'actor_id' => auth()->id(), 'receipt' => 'RC-'.strtoupper(Str::random(10)), 'amount_minor' => $amount, 'method' => $method, 'collected_at' => now()]);
        $order->paid_minor += $amount;
        $order->save();
        $this->audit->record('collection.received', $receipt, ['order_id' => $order->id, 'amount_minor' => $amount, 'method' => $method]);

        return $receipt;
    }
}
