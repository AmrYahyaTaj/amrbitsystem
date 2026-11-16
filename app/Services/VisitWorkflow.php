<?php

namespace App\Services;

use App\Enums\VisitStatus;
use App\Models\Visit;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VisitWorkflow
{
    public function __construct(private AuditRecorder $audit) {}

    public function checkIn(Visit $visit, array $location): void
    {
        DB::transaction(function () use ($visit, $location) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->expect($visit, VisitStatus::Planned);
            $customer = $visit->customer;
            if (! $customer->is_active) {
                throw ValidationException::withMessages(['location' => 'هذا العميل غير نشط.']);
            }
            $localToday = now()->timezone(app(CompanyContext::class)->company->timezone)->toDateString();
            if ($visit->scheduled_for->toDateString() !== $localToday) {
                throw ValidationException::withMessages(['location' => 'بدء الزيارة متاح في يومها المحدد فقط.']);
            }
            $captured = CarbonImmutable::parse($location['captured_at']);
            $age = now()->getTimestamp() - $captured->getTimestamp();
            if ($age > 120 || $age < -15 || $location['accuracy_m'] > 100) {
                throw ValidationException::withMessages(['location' => 'الموقع قديم أو دقته ضعيفة. حدّث موقعك وحاول مرة ثانية.']);
            }
            $distance = $this->distance((float) $customer->latitude, (float) $customer->longitude, (float) $location['latitude'], (float) $location['longitude']);
            if ($distance > $customer->geofence_radius_m) {
                throw ValidationException::withMessages(['location' => 'موقعك خارج نطاق العميل المسموح.']);
            }
            $visit->status = VisitStatus::InProgress;
            $visit->checked_in_at = now();
            $visit->checkin_latitude = $location['latitude'];
            $visit->checkin_longitude = $location['longitude'];
            $visit->accuracy_m = $location['accuracy_m'];
            $visit->distance_m = round($distance, 2);
            $visit->location_captured_at = $captured;
            $visit->location_verified = true;
            $visit->save();
            $this->audit->record('visit.checked_in', $visit, ['distance_m' => round($distance, 2)]);
        });
    }

    public function finish(Visit $visit, VisitStatus $status, ?string $note, ?string $reasonCode = null): void
    {
        DB::transaction(function () use ($visit, $status, $note, $reasonCode) {
            $visit = Visit::query()->lockForUpdate()->findOrFail($visit->id);
            $this->expect($visit, $status === VisitStatus::Skipped ? VisitStatus::Planned : VisitStatus::InProgress);
            if ($status === VisitStatus::Completed && ! $visit->location_verified) {
                throw ValidationException::withMessages(['status' => 'إكمال الزيارة يتطلب إثبات الموقع أولًا.']);
            }
            $visit->status = $status;
            $visit->outcome_note = $note;
            $visit->reason_code = $reasonCode;
            $visit->finished_at = now();
            $visit->save();
            $this->audit->record('visit.'.$status->value, $visit);
        });
    }

    private function expect(Visit $visit, VisitStatus $expected): void
    {
        if ($visit->status !== $expected) {
            throw ValidationException::withMessages(['status' => 'حالة الزيارة تغيرت أو هذه العملية غير مسموحة. حدّث الصفحة.']);
        }
    }

    public function distance(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $a = sin(deg2rad($lat2 - $lat1) / 2) ** 2
            + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin(deg2rad($lon2 - $lon1) / 2) ** 2;

        return 6371000 * 2 * asin(sqrt(min(1, max(0, $a))));
    }
}
