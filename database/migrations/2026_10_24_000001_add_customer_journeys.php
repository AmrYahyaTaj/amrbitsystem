<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->unsignedBigInteger('representative_id')->nullable();
            $t->text('service_notes')->nullable();
            $t->index(['company_id', 'representative_id', 'is_active']);
            $t->foreign(['company_id', 'representative_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::create('weekly_plans', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('representative_id');
            $t->unsignedBigInteger('vehicle_id');
            $t->unsignedTinyInteger('weekday'); // Sunday=0, Saturday=6, independent of display order.
            $t->json('customer_ids');
            $t->decimal('origin_latitude', 10, 7);
            $t->decimal('origin_longitude', 10, 7);
            $t->string('origin_label', 120);
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['company_id', 'representative_id', 'weekday']);
            $t->unique(['id', 'company_id']);
            $t->foreign(['company_id', 'representative_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->foreign(['vehicle_id', 'company_id'])->references(['id', 'company_id'])->on('vehicles')->restrictOnDelete();
        });
        Schema::table('trips', function (Blueprint $t) {
            $t->unsignedBigInteger('weekly_plan_id')->nullable();
            $t->unsignedInteger('weekly_plan_version')->nullable();
            $t->json('route_snapshot')->nullable();
            $t->unique(['company_id', 'weekly_plan_id', 'scheduled_for']);
            $t->foreign(['weekly_plan_id', 'company_id'])->references(['id', 'company_id'])->on('weekly_plans')->restrictOnDelete();
        });
        Schema::table('visits', function (Blueprint $t) {
            $t->string('reason_code', 40)->nullable();
        });

        // Infer only a single, active, area-authorized historical representative.
        // Ambiguous/unassigned customers stay unassigned for explicit manager review.
        DB::table('customers')->orderBy('id')->chunkById(100, function ($customers) {
            foreach ($customers as $customer) {
                $owners = DB::table('visits')->where('company_id', $customer->company_id)->where('customer_id', $customer->id)->where('status', '!=', 'cancelled')->pluck('representative_id')
                    ->merge(DB::table('orders')->where('company_id', $customer->company_id)->where('customer_id', $customer->id)->pluck('representative_id'))->unique()->values();
                if ($owners->count() !== 1) {
                    continue;
                }
                $member = DB::table('company_user')->where('company_id', $customer->company_id)->where('user_id', $owners[0])->where('role', 'representative')->where('is_active', true)->first();
                if (! $member) {
                    continue;
                }
                if ($member->area_access !== 'all' && ! DB::table('representative_areas')->where('company_id', $customer->company_id)->where('user_id', $owners[0])->where('area_id', $customer->area_id)->exists()) {
                    continue;
                }
                DB::table('customers')->where('id', $customer->id)->update(['representative_id' => $owners[0]]);
            }
        });
    }

    public function down(): void
    {
        Schema::table('visits', fn (Blueprint $t) => $t->dropColumn('reason_code'));
        Schema::table('trips', function (Blueprint $t) {
            $t->dropForeign(['weekly_plan_id', 'company_id']);
            $t->dropUnique(['company_id', 'weekly_plan_id', 'scheduled_for']);
            $t->dropColumn(['weekly_plan_id', 'weekly_plan_version', 'route_snapshot']);
        });
        Schema::dropIfExists('weekly_plans');
        Schema::table('customers', function (Blueprint $t) {
            $t->dropForeign(['company_id', 'representative_id']);
            $t->dropIndex(['company_id', 'representative_id', 'is_active']);
            $t->dropColumn(['representative_id', 'service_notes']);
        });
    }
};
