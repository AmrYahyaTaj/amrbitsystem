<?php

namespace App\Models;

class ArchiveDocument extends TenantModel
{
    protected $guarded = ['id', 'company_id'];

    protected $attributes = ['current_version' => 0, 'version' => 1, 'status' => 'pending_review'];

    protected function casts(): array
    {
        return ['document_date' => 'date', 'expires_on' => 'date', 'retain_until' => 'date'];
    }

    public function department()
    {
        return $this->belongsTo(ArchiveDepartment::class);
    }

    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function versions()
    {
        return $this->hasMany(ArchiveVersion::class, 'document_id');
    }
}
