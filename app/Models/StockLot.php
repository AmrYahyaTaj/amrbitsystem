<?php

namespace App\Models;

class StockLot extends TenantModel
{
    protected $attributes = ['quality_version' => 1];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['manufactured_on' => 'date', 'expires_on' => 'date'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function production()
    {
        return $this->belongsTo(ProductionOrder::class, 'production_order_id');
    }
}
