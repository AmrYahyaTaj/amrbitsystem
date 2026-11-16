<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrderItem extends TenantModel
{
    protected $fillable = ['order_id', 'product_id', 'quantity', 'unit_price_minor', 'total_minor'];

    public $timestamps = false;

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'product_id');
    }
}
