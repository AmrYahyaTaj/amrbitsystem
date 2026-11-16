<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $t) {
            $t->unsignedInteger('capacity_units')->default(500);
        });
        Schema::create('warehouses', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('name');
            $t->string('type')->default('warehouse');
            $t->unsignedBigInteger('vehicle_id')->nullable();
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->foreign(['vehicle_id', 'company_id'])->references(['id', 'company_id'])->on('vehicles')->restrictOnDelete();
        });
        Schema::create('products', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('sku');
            $t->string('name');
            $t->string('unit')->default('قطعة');
            $t->unsignedBigInteger('price_minor');
            $t->unsignedInteger('reorder_level')->default(20);
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->unique(['company_id', 'sku']);
            $t->unique(['id', 'company_id']);
        });
        Schema::create('trips', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code');
            $t->unsignedBigInteger('representative_id');
            $t->unsignedBigInteger('vehicle_id');
            $t->unsignedBigInteger('warehouse_id');
            $t->date('scheduled_for');
            $t->string('status')->default('planned');
            $t->timestamp('started_at')->nullable();
            $t->timestamp('closed_at')->nullable();
            $t->unsignedBigInteger('active_representative_id')->nullable();
            $t->unsignedBigInteger('active_vehicle_id')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'code']);
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'active_representative_id']);
            $t->unique(['company_id', 'active_vehicle_id']);
            $t->foreign(['company_id', 'representative_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            foreach (['vehicle_id' => 'vehicles', 'warehouse_id' => 'warehouses'] as $column => $table) {
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
            $t->index(['company_id', 'scheduled_for', 'status']);
        });
        Schema::table('visits', function (Blueprint $t) {
            $t->unsignedBigInteger('trip_id')->nullable();
            $t->unsignedInteger('sequence')->default(1);
            $t->time('window_start')->nullable();
            $t->time('window_end')->nullable();
            $t->unsignedInteger('service_minutes')->default(10);
            $t->foreign(['trip_id', 'company_id'])->references(['id', 'company_id'])->on('trips')->restrictOnDelete();
        });
        Schema::create('location_points', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('trip_id');
            $t->foreignId('user_id')->constrained();
            $t->uuid('event_id');
            $t->decimal('latitude', 10, 7);
            $t->decimal('longitude', 10, 7);
            $t->unsignedInteger('accuracy_m');
            $t->decimal('speed_kmh', 8, 2)->nullable();
            $t->timestamp('captured_at');
            $t->timestamp('received_at');
            $t->string('source')->default('web');
            $t->string('quality')->default('accepted');
            $t->unique(['company_id', 'event_id']);
            $t->foreign(['trip_id', 'company_id'])->references(['id', 'company_id'])->on('trips')->restrictOnDelete();
            $t->index(['company_id', 'trip_id', 'captured_at']);
        });
        Schema::create('stock_balances', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('quantity')->default(0);
            $t->timestamps();
            $t->unique(['company_id', 'warehouse_id', 'product_id']);
            foreach (['warehouse_id' => 'warehouses', 'product_id' => 'products'] as $c => $table) {
                $t->foreign([$c, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('stock_movements', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('warehouse_id');
            $t->unsignedBigInteger('product_id');
            $t->integer('quantity');
            $t->string('reason');
            $t->string('reference');
            $t->foreignId('actor_id')->nullable()->constrained('users');
            $t->timestamp('created_at');
            foreach (['warehouse_id' => 'warehouses', 'product_id' => 'products'] as $c => $table) {
                $t->foreign([$c, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
            $t->index(['company_id', 'warehouse_id', 'created_at']);
        });
        Schema::create('orders', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code');
            $t->unsignedBigInteger('customer_id');
            $t->foreignId('representative_id')->constrained('users');
            $t->unsignedBigInteger('trip_id')->nullable();
            $t->unsignedBigInteger('warehouse_id');
            $t->string('status')->default('draft');
            $t->unsignedBigInteger('total_minor')->default(0);
            $t->unsignedBigInteger('paid_minor')->default(0);
            $t->timestamps();
            $t->unique(['company_id', 'code']);
            $t->unique(['id', 'company_id']);
            foreach (['customer_id' => 'customers', 'trip_id' => 'trips', 'warehouse_id' => 'warehouses'] as $c => $table) {
                $t->foreign([$c, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('order_items', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('order_id');
            $t->unsignedBigInteger('product_id');
            $t->unsignedInteger('quantity');
            $t->unsignedBigInteger('unit_price_minor');
            $t->unsignedBigInteger('total_minor');
            foreach (['order_id' => 'orders', 'product_id' => 'products'] as $c => $table) {
                $t->foreign([$c, 'company_id'])->references(['id', 'company_id'])->on($table)->restrictOnDelete();
            }
        });
        Schema::create('collections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('order_id');
            $t->foreignId('actor_id')->constrained('users');
            $t->string('receipt');
            $t->unsignedBigInteger('amount_minor');
            $t->string('method');
            $t->timestamp('collected_at');
            $t->unique(['company_id', 'receipt']);
            $t->foreign(['order_id', 'company_id'])->references(['id', 'company_id'])->on('orders')->restrictOnDelete();
        });
        Schema::create('operation_receipts', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained();
            $t->uuid('request_id');
            $t->string('action');
            $t->string('payload_hash', 64);
            $t->json('result')->nullable();
            $t->timestamps();
            $t->unique(['company_id', 'action', 'request_id']);
        });
    }

    public function down(): void
    {
        foreach (['operation_receipts', 'collections', 'order_items', 'orders', 'stock_movements', 'stock_balances', 'location_points'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('visits', function (Blueprint $t) {
            $t->dropForeign(['trip_id', 'company_id']);
            $t->dropColumn(['trip_id', 'sequence', 'window_start', 'window_end', 'service_minutes']);
        });
        foreach (['trips', 'products', 'warehouses'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('vehicles', fn (Blueprint $t) => $t->dropColumn('capacity_units'));
    }
};
