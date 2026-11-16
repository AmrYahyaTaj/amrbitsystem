<?php

namespace App\Models;

class WorkspaceNotification extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id', 'company_id'];

    protected function casts(): array
    {
        return ['read_at' => 'datetime', 'created_at' => 'datetime'];
    }
}
