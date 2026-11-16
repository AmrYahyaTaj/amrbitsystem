<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Company extends Model
{
    protected $attributes = ['timezone' => 'Asia/Riyadh', 'currency' => 'SAR', 'industry' => 'distribution', 'is_active' => true];

    protected $fillable = ['name', 'slug', 'industry', 'timezone', 'currency', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }
}
