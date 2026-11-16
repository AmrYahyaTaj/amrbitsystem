<?php

namespace App\Models;

class LocationPoint extends TenantModel
{
    protected $fillable = ['trip_id', 'user_id', 'event_id', 'latitude', 'longitude', 'accuracy_m', 'speed_kmh', 'captured_at', 'received_at', 'source', 'quality'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['captured_at' => 'datetime', 'received_at' => 'datetime'];
    }
}
