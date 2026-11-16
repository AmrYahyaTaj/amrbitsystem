<?php

namespace App\Models;

class ArchiveDepartment extends TenantModel
{
    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['is_active' => true, 'version' => 1];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }
}
