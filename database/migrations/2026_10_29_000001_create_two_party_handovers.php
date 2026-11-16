<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('drivers', function (Blueprint $t) {
            $t->unsignedBigInteger('user_id')->nullable();
            $t->unique(['company_id', 'user_id']);
            $t->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::create('handover_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 40);
            $t->string('kind', 40);
            $t->string('status', 20)->default('pending');
            $t->unsignedBigInteger('sender_id');
            $t->unsignedBigInteger('recipient_id')->nullable();
            $t->unsignedBigInteger('created_by');
            foreach (['sender_id', 'recipient_id', 'created_by'] as $column) {
                $t->foreign(['company_id', $column])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            }
            $t->foreignId('vehicle_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('driver_id')->nullable()->constrained()->restrictOnDelete();
            $t->foreignId('assignment_id')->nullable()->constrained('vehicle_assignments')->restrictOnDelete();
            $t->foreignId('cash_handover_id')->nullable()->constrained('cash_handovers')->restrictOnDelete();
            $t->foreignId('from_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $t->foreignId('to_warehouse_id')->nullable()->constrained('warehouses')->restrictOnDelete();
            $t->unsignedInteger('reserved_units')->default(0);
            $t->json('snapshot');
            $t->text('sender_note');
            $t->text('response_note')->nullable();
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->foreign(['company_id', 'decided_by'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->timestamp('decided_at')->nullable();
            $t->json('receipt')->nullable();
            $t->string('receipt_hash', 64)->nullable();
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            $t->unique(['company_id', 'cash_handover_id']);
            $t->index(['company_id', 'recipient_id', 'status']);
            $t->index(['company_id', 'kind', 'status']);
        });
        Schema::create('handover_locks', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('handover_id');
            $t->string('resource_key', 100);
            $t->unique(['company_id', 'resource_key']);
            $t->foreign(['handover_id', 'company_id'])->references(['id', 'company_id'])->on('handover_requests')->restrictOnDelete();
        });
        Schema::create('handover_stock_reservations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('handover_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('quantity');
            $t->timestamp('released_at')->nullable();
            $t->foreign(['handover_id', 'company_id'])->references(['id', 'company_id'])->on('handover_requests')->restrictOnDelete();
            foreach (['warehouse_id' => 'warehouses', 'product_id' => 'products'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
            $t->unique(['company_id', 'handover_id', 'product_id']);
            $t->index(['company_id', 'warehouse_id', 'product_id', 'released_at'], 'custody_stock_available_index');
        });
        Schema::create('handover_messages', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('handover_id');
            $t->unsignedBigInteger('user_id');
            $t->text('body');
            $t->timestamp('created_at');
            $t->foreign(['handover_id', 'company_id'])->references(['id', 'company_id'])->on('handover_requests')->restrictOnDelete();
            $t->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::create('handover_files', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('handover_id');
            $t->unsignedBigInteger('user_id');
            $t->string('original_name', 200);
            $t->string('path');
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->timestamp('created_at');
            $t->foreign(['handover_id', 'company_id'])->references(['id', 'company_id'])->on('handover_requests')->restrictOnDelete();
            $t->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::create('workspace_notifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('user_id');
            $t->unsignedBigInteger('handover_id');
            $t->string('event', 40);
            $t->string('title', 200);
            $t->text('body');
            $t->timestamp('read_at')->nullable();
            $t->timestamp('created_at');
            $t->foreign(['handover_id', 'company_id'])->references(['id', 'company_id'])->on('handover_requests')->restrictOnDelete();
            $t->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->index(['company_id', 'user_id', 'read_at', 'id'], 'workspace_notifications_inbox_index');
        });
    }

    public function down(): void
    {
        foreach (['workspace_notifications', 'handover_files', 'handover_messages', 'handover_stock_reservations', 'handover_locks', 'handover_requests'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('drivers', function (Blueprint $t) {
            $t->dropForeign(['company_id', 'user_id']);
            $t->dropUnique(['company_id', 'user_id']);
            $t->dropColumn('user_id');
        });
    }
};
