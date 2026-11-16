<?php

namespace App\Models;

class Driver extends TenantModel
{
    protected $fillable = ['name', 'phone', 'employee_code', 'license_number', 'license_expires_on', 'status', 'notes', 'user_id'];

    protected function casts(): array
    {
        return ['license_expires_on' => 'date:Y-m-d', 'user_id' => 'integer'];
    }
}
