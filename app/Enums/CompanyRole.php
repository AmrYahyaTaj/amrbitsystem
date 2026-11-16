<?php

namespace App\Enums;

enum CompanyRole: string
{
    case Manager = 'manager';
    case Supervisor = 'supervisor';
    case Representative = 'representative';
    case Accountant = 'accountant';
    case Viewer = 'viewer';
    case Driver = 'driver';
    case WarehouseKeeper = 'warehouse_keeper';
    case Production = 'production';
    case QualityInspector = 'quality_inspector';
    case QualityManager = 'quality_manager';

    public function label(): string
    {
        return match ($this) {
            self::Manager => 'مدير الشركة',
            self::Supervisor => 'مشرف العمليات',
            self::Representative => 'مندوب',
            self::Accountant => 'محاسب',
            self::Viewer => 'مشاهد',
            self::Driver => 'سائق',
            self::WarehouseKeeper => 'أمين المستودع',
            self::Production => 'مسؤول الإنتاج',
            self::QualityInspector => 'فاحص الجودة',
            self::QualityManager => 'معتمد الجودة',
        };
    }

    public function managesOperations(): bool
    {
        return in_array($this, [self::Manager, self::Supervisor], true);
    }

    public function seesOperations(): bool
    {
        return in_array($this, [self::Manager, self::Supervisor, self::Viewer], true);
    }
}
