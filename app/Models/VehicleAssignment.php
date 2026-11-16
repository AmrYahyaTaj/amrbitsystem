<?php

namespace App\Models;

class VehicleAssignment extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['vehicle_id', 'driver_id', 'active_vehicle_id', 'active_driver_id', 'assigned_by', 'returned_by', 'assigned_at', 'returned_at', 'handover_note', 'return_note'];

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    protected function casts(): array
    {
        return ['assigned_at' => 'datetime', 'returned_at' => 'datetime'];
    }
}
