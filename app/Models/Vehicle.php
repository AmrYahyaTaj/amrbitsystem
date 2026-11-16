<?php

namespace App\Models;

class Vehicle extends TenantModel
{
    public const TYPES = ['van' => 'فان', 'pickup' => 'وانيت / بيك أب', 'light_truck' => 'دينا / شاحنة خفيفة', 'truck' => 'شاحنة', 'tractor_trailer' => 'تريلا / رأس ومقطورة', 'car' => 'سيارة صغيرة', 'motorcycle' => 'دراجة نارية', 'other' => 'نوع آخر'];

    protected $fillable = ['plate', 'name', 'status', 'capacity_units', 'vehicle_type', 'make', 'model', 'model_year', 'vin', 'ownership', 'payload_kg', 'volume_m3', 'odometer_km', 'registration_expires_on', 'insurance_expires_on', 'notes'];
}
