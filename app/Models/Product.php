<?php

namespace App\Models;

class Product extends TenantModel
{
    protected $fillable = ['sku', 'name', 'unit', 'price_minor', 'reorder_level', 'is_active'];

    protected function casts(): array
    {
        return ['price_minor' => 'integer', 'reorder_level' => 'integer', 'is_active' => 'boolean'];
    }
}
