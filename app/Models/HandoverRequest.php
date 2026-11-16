<?php

namespace App\Models;

class HandoverRequest extends TenantModel
{
    public const KINDS = ['vehicle_delivery' => 'تسليم مركبة', 'vehicle_return' => 'إرجاع مركبة', 'stock_transfer' => 'تسليم حمولة ومخزون', 'asset' => 'عهدة ومعدات', 'cash' => 'تسليم نقد'];

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['snapshot' => 'array', 'receipt' => 'array', 'decided_at' => 'datetime'];
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }

    public function sender()
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    public function recipient()
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
