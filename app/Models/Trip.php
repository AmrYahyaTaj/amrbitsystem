<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Trip extends TenantModel
{
    protected $fillable = ['code', 'representative_id', 'vehicle_id', 'warehouse_id', 'scheduled_for', 'driver_id'];

    protected function casts(): array
    {
        return ['scheduled_for' => 'date', 'started_at' => 'datetime', 'closed_at' => 'datetime', 'cancelled_at' => 'datetime', 'plan_version' => 'integer'];
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class, 'vehicle_id');
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class, 'warehouse_id');
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function driver(): BelongsTo
    {
        return $this->belongsTo(Driver::class);
    }
}
