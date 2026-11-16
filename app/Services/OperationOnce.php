<?php

namespace App\Services;

use App\Support\CompanyContext;
use Illuminate\Support\Facades\DB;

class OperationOnce
{
    public function run(string $action, string $requestId, array $payload, callable $work): array
    {
        $normalize = function (array $value) use (&$normalize): array {
            if (! array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $normalize($item);
                }
            }

            return $value;
        };
        $hash = hash('sha256', json_encode($normalize($payload), JSON_THROW_ON_ERROR));
        $key = ['company_id' => app(CompanyContext::class)->id(), 'action' => $action, 'request_id' => $requestId];

        return DB::transaction(function () use ($key, $hash, $work) {
            DB::table('operation_receipts')->insertOrIgnore($key + ['user_id' => auth()->id(), 'payload_hash' => $hash, 'created_at' => now(), 'updated_at' => now()]);
            $receipt = DB::table('operation_receipts')->where($key)->lockForUpdate()->first();
            abort_unless($receipt && $receipt->user_id === auth()->id() && hash_equals($receipt->payload_hash, $hash), 409, 'هذا المعرف مستخدم لعملية أخرى.');
            if ($receipt->result !== null) {
                return json_decode($receipt->result, true, flags: JSON_THROW_ON_ERROR);
            }
            $result = $work();
            DB::table('operation_receipts')->where('id', $receipt->id)->update(['result' => json_encode($result, JSON_THROW_ON_ERROR), 'updated_at' => now()]);

            return $result;
        }, 3);
    }
}
