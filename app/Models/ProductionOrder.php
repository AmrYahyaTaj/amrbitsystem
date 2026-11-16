<?php

namespace App\Models;

class ProductionOrder extends TenantModel
{
    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['scheduled_on' => 'date', 'started_at' => 'datetime', 'completed_at' => 'datetime'];
    }

    public function product()
    {
        return $this->belongsTo(Product::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function operator()
    {
        return $this->belongsTo(User::class, 'operator_id');
    }
}
