<?php

namespace App\Models;

class WeeklyPlan extends TenantModel
{
    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['customer_ids' => 'array', 'weekday' => 'integer', 'version' => 'integer', 'is_active' => 'boolean', 'origin_latitude' => 'decimal:7', 'origin_longitude' => 'decimal:7'];
    }

    public function representative()
    {
        return $this->belongsTo(User::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
