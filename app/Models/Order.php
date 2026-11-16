<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Order extends TenantModel
{
    protected $fillable = ['code', 'customer_id', 'representative_id', 'trip_id', 'warehouse_id', 'payment_terms', 'due_date', 'initial_paid_minor'];

    protected function casts(): array
    {
        return ['total_minor' => 'integer', 'paid_minor' => 'integer', 'initial_paid_minor' => 'integer'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }
}
