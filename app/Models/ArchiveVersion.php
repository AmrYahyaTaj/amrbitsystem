<?php

namespace App\Models;

class ArchiveVersion extends TenantModel
{
    public $timestamps = false;

    protected $guarded = ['id', 'company_id'];

    protected $hidden = ['path'];

    protected $attributes = ['status' => 'pending_review'];

    protected function casts(): array
    {
        return ['signed_copy' => 'boolean', 'created_at' => 'datetime', 'decided_at' => 'datetime', 'metadata_snapshot' => 'array'];
    }

    public function uploader()
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function decider()
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
