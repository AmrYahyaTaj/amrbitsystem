<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('production_orders', function (Blueprint $t) {
            $t->unsignedBigInteger('completed_by')->nullable();
            $t->foreign(['company_id', 'completed_by'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::table('stock_lots', fn (Blueprint $t) => $t->unsignedInteger('quality_version')->default(1));
        Schema::create('quality_site_access', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->timestamps();
            $t->unique(['company_id', 'user_id', 'warehouse_id'], 'quality_site_member_unique');
            $t->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->foreign(['warehouse_id', 'company_id'])->references(['id', 'company_id'])->on('warehouses')->restrictOnDelete();
        });
        Schema::create('quality_inspections', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->string('code', 40);
            $t->unsignedBigInteger('lot_id');
            $t->unsignedBigInteger('active_lot_id')->nullable();
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('requested_by');
            $t->unsignedBigInteger('inspected_by')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->string('status', 30)->default('awaiting_inspection');
            $t->unsignedInteger('version')->default(1);
            $t->unsignedInteger('lot_version');
            $t->unsignedInteger('sample_size')->nullable();
            $t->json('checks')->nullable();
            $t->string('recommendation', 30)->nullable();
            $t->text('request_note');
            $t->text('inspection_note')->nullable();
            $t->text('decision_note')->nullable();
            $t->string('decision', 30)->nullable();
            $t->timestamp('inspected_at')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('due_at');
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            $t->unique(['company_id', 'active_lot_id'], 'quality_one_active_lot');
            $t->index(['company_id', 'status', 'due_at']);
            foreach (['lot_id' => 'stock_lots', 'active_lot_id' => 'stock_lots', 'warehouse_id' => 'warehouses'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
            foreach (['requested_by', 'inspected_by', 'decided_by'] as $column) {
                $t->foreign(['company_id', $column])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            }
        });
        Schema::table('workspace_notifications', function (Blueprint $t) {
            $t->unsignedBigInteger('handover_id')->nullable()->change();
            $t->unsignedBigInteger('quality_inspection_id')->nullable();
            $t->foreign(['quality_inspection_id', 'company_id'], 'notification_quality_company_fk')->references(['id', 'company_id'])->on('quality_inspections')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        // Preserve audit history: rollback is deliberately blocked after business records exist.
        if (DB::table('quality_inspections')->exists()) {
            throw new RuntimeException('Quality history exists. Restore a verified backup instead of deleting approved records.');
        }
        Schema::table('workspace_notifications', function (Blueprint $t) {
            $t->dropForeign('notification_quality_company_fk');
            $t->dropColumn('quality_inspection_id');
            $t->unsignedBigInteger('handover_id')->nullable(false)->change();
        });
        Schema::dropIfExists('quality_inspections');
        Schema::dropIfExists('quality_site_access');
        Schema::table('stock_lots', fn (Blueprint $t) => $t->dropColumn('quality_version'));
        Schema::table('production_orders', function (Blueprint $t) {
            $t->dropForeign(['company_id', 'completed_by']);
            $t->dropColumn('completed_by');
        });
    }
};
