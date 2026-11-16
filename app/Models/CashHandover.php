<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashHandover extends TenantModel
{
    protected $fillable = ['code', 'collector_id', 'requested_by', 'amount_minor', 'reference', 'note'];

    protected function casts(): array
    {
        return ['amount_minor' => 'integer', 'reviewed_at' => 'datetime'];
    }

    public function collector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'collector_id');
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
