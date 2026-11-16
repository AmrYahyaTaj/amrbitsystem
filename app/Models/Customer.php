<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Customer extends TenantModel
{
    protected $fillable = ['area_id', 'code', 'name', 'phone', 'address', 'latitude', 'longitude', 'geofence_radius_m', 'is_active', 'representative_id', 'service_notes'];

    protected function casts(): array
    {
        return ['latitude' => 'decimal:7', 'longitude' => 'decimal:7', 'is_active' => 'boolean'];
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
