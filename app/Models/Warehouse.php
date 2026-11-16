<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Warehouse extends TenantModel
{
    protected $fillable = ['name', 'type', 'vehicle_id', 'code', 'custodian_id', 'address', 'phone', 'notes', 'is_active', 'capacity_units'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'capacity_units' => 'integer', 'custodian_id' => 'integer'];
    }

    public function custodian()
    {
        return $this->belongsTo(User::class, 'custodian_id');
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }
}
