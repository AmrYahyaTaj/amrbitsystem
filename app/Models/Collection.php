<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Collection extends TenantModel
{
    protected $fillable = ['order_id', 'actor_id', 'receipt', 'amount_minor', 'method', 'collected_at'];

    public $timestamps = false;

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'collected_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class, 'order_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
