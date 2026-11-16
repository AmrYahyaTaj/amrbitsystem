<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Model;

class AuditRecorder
{
    public function record(string $action, Model $subject, array $metadata = []): void
    {
        AuditLog::create([
            'actor_id' => auth()->id(), 'action' => $action,
            'subject_type' => $subject->getTable(), 'subject_id' => $subject->id,
            'metadata' => $metadata, 'created_at' => now(),
        ]);
    }
}
