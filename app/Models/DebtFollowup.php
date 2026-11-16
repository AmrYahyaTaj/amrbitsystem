<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DebtFollowup extends TenantModel
{
    public $timestamps = false;

    protected $fillable = ['order_id', 'actor_id', 'outcome', 'note', 'next_followup_date', 'promised_minor', 'created_at'];

    protected function casts(): array
    {
        return ['promised_minor' => 'integer', 'created_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
