<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends TenantModel
{
    protected $fillable = ['warehouse_id', 'product_id', 'quantity', 'reason', 'reference', 'actor_id', 'created_at'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['created_at' => 'datetime', 'quantity' => 'integer'];
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
