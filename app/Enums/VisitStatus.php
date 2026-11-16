<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Planned = 'planned';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Planned => 'مجدولة',
            self::InProgress => 'قيد التنفيذ',
            self::Completed => 'مكتملة',
            self::Failed => 'لم تتم الخدمة',
            self::Skipped => 'متجاوزة',
            self::Cancelled => 'ملغاة من الخطة',
        };
    }
}
