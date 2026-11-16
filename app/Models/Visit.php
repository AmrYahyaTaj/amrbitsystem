<?php

namespace App\Models;

use App\Enums\VisitStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Visit extends TenantModel
{
    protected $fillable = ['customer_id', 'representative_id', 'scheduled_for', 'trip_id', 'sequence', 'window_start', 'window_end', 'service_minutes'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'scheduled_for' => 'date',
            'checked_in_at' => 'datetime',
            'finished_at' => 'datetime',
            'location_captured_at' => 'datetime',
            'location_verified' => 'boolean',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function representative(): BelongsTo
    {
        return $this->belongsTo(User::class, 'representative_id');
    }
}
