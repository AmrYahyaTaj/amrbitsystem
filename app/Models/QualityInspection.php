<?php

namespace App\Models;

class QualityInspection extends TenantModel
{
    protected $attributes = ['status' => 'awaiting_inspection', 'version' => 1];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['checks' => 'array', 'due_at' => 'datetime', 'inspected_at' => 'datetime', 'decided_at' => 'datetime'];
    }

    public function lot()
    {
        return $this->belongsTo(StockLot::class);
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function requester()
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function inspector()
    {
        return $this->belongsTo(User::class, 'inspected_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
