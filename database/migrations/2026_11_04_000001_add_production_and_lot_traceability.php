<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $t) {
            $t->string('code', 40)->nullable();
            $t->unsignedBigInteger('custodian_id')->nullable();
            $t->string('address', 300)->nullable();
            $t->string('phone', 30)->nullable();
            $t->text('notes')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('capacity_units')->nullable();
            $t->unique(['company_id', 'code']);
            $t->foreign(['company_id', 'custodian_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::table('stock_movements', fn (Blueprint $t) => $t->unique(['id', 'company_id']));
        Schema::create('production_orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code', 40);
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('operator_id');
            $t->unsignedBigInteger('created_by');
            $t->string('line', 100);
            $t->string('shift', 60);
            $t->date('scheduled_on');
            $t->unsignedInteger('planned_quantity');
            $t->unsignedInteger('good_quantity')->default(0);
            $t->unsignedInteger('scrap_quantity')->default(0);
            $t->string('status', 30)->default('planned');
            $t->unsignedInteger('version')->default(1);
            $t->text('notes');
            $t->text('quality_note')->nullable();
            $t->text('closing_note')->nullable();
            $t->timestamp('started_at')->nullable();
            $t->timestamp('completed_at')->nullable();
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            foreach (['warehouse_id' => 'warehouses', 'product_id' => 'products'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
            foreach (['operator_id', 'created_by'] as $column) {
                $t->foreign(['company_id', $column])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            }
        });
        Schema::create('stock_lots', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('product_id');
            $t->unsignedBigInteger('origin_warehouse_id');
            $t->unsignedBigInteger('production_order_id')->nullable();
            $t->string('code', 80);
            $t->string('source', 30);
            $t->string('status', 30)->default('released');
            $t->date('manufactured_on')->nullable();
            $t->date('expires_on')->nullable();
            $t->text('notes')->nullable();
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            foreach (['product_id' => 'products', 'origin_warehouse_id' => 'warehouses', 'production_order_id' => 'production_orders'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('stock_lot_balances', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('lot_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedInteger('quantity')->default(0);
            $t->unique(['company_id', 'lot_id', 'warehouse_id']);
            foreach (['lot_id' => 'stock_lots', 'warehouse_id' => 'warehouses'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('stock_lot_entries', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('lot_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('stock_movement_id')->nullable();
            $t->integer('quantity');
            $t->string('reason', 40);
            $t->string('reference', 100);
            $t->timestamp('created_at');
            foreach (['lot_id' => 'stock_lots', 'warehouse_id' => 'warehouses', 'stock_movement_id' => 'stock_movements'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
            $t->index(['company_id', 'reference']);
        });
        Schema::create('handover_lot_reservations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('handover_id');
            $t->unsignedBigInteger('lot_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedInteger('quantity');
            $t->timestamp('released_at')->nullable();
            $t->unique(['company_id', 'handover_id', 'lot_id']);
            foreach (['handover_id' => 'handover_requests', 'lot_id' => 'stock_lots', 'warehouse_id' => 'warehouses'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('production_materials', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('production_order_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('quantity');
            $t->json('lots');
            foreach (['production_order_id' => 'production_orders', 'product_id' => 'products'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('delivery_proofs', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('recorded_by');
            $t->string('receiver_name', 150);
            $t->text('note');
            $t->string('path');
            $t->string('original_name', 200);
            $t->string('mime', 100);
            $t->string('sha256', 64);
            $t->unsignedBigInteger('size');
            $t->timestamp('created_at');
            $t->foreign(['order_id', 'company_id'])->references(['id', 'company_id'])->on('orders')->restrictOnDelete();
            $t->foreign(['company_id', 'recorded_by'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        // Record only the present opening balance. Never invent old batch provenance.
        DB::table('stock_balances')->where('quantity', '>', 0)->orderBy('id')->chunkById(200, function ($rows) {
            foreach ($rows as $row) {
                $lot = DB::table('stock_lots')->insertGetId(['company_id' => $row->company_id, 'product_id' => $row->product_id, 'origin_warehouse_id' => $row->warehouse_id, 'code' => 'LEG-'.$row->warehouse_id.'-'.$row->product_id, 'source' => 'legacy', 'status' => 'released', 'notes' => 'رصيد افتتاحي سابق لتفعيل تتبع الدفعات؛ مصدره التاريخي غير موثق.', 'created_at' => now(), 'updated_at' => now()]);
                DB::table('stock_lot_balances')->insert(['company_id' => $row->company_id, 'lot_id' => $lot, 'warehouse_id' => $row->warehouse_id, 'quantity' => $row->quantity]);
                DB::table('stock_lot_entries')->insert(['company_id' => $row->company_id, 'lot_id' => $lot, 'warehouse_id' => $row->warehouse_id, 'quantity' => $row->quantity, 'reason' => 'legacy_opening', 'reference' => 'TRACE-OPENING', 'created_at' => now()]);
            }
        });
    }

    public function down(): void
    {
        foreach (['delivery_proofs', 'production_materials', 'handover_lot_reservations', 'stock_lot_entries', 'stock_lot_balances', 'stock_lots', 'production_orders'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('stock_movements', fn (Blueprint $t) => $t->dropUnique(['id', 'company_id']));
        Schema::table('warehouses', function (Blueprint $t) {
            $t->dropForeign(['company_id', 'custodian_id']);
            $t->dropUnique(['company_id', 'code']);
            $t->dropColumn(['code', 'custodian_id', 'address', 'phone', 'notes', 'is_active', 'capacity_units']);
        });
    }
};
