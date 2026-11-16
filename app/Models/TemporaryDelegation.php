<?php

namespace App\Models;

class TemporaryDelegation extends TenantModel
{
    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['status' => 'pending', 'version' => 1];

    protected function casts(): array
    {
        return ['starts_at' => 'datetime', 'ends_at' => 'datetime', 'responded_at' => 'datetime', 'revoked_at' => 'datetime'];
    }

    public function principal()
    {
        return $this->belongsTo(User::class, 'principal_id');
    }

    public function delegate()
    {
        return $this->belongsTo(User::class, 'delegate_id');
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function warehouse()
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function department()
    {
        return $this->belongsTo(ArchiveDepartment::class);
    }
}
