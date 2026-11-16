<?php

namespace App\Models;

class HandoverFile extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id', 'company_id'];

    protected $hidden = ['path'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }
}
