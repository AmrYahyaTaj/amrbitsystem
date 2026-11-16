<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('industry')->default('distribution');
            $table->string('timezone')->default('Asia/Riyadh');
            $table->char('currency', 3)->default('SAR');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::create('company_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->string('role', 30)->default('viewer');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'user_id']);
        });
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('city');
            $table->timestamps();
            $table->unique(['company_id', 'name']);
            $table->unique(['id', 'company_id']);
        });
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('area_id');
            $table->string('code', 40);
            $table->string('name');
            $table->string('phone', 30)->nullable();
            $table->string('address', 500);
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->unsignedInteger('geofence_radius_m')->default(150);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->unique(['company_id', 'code']);
            $table->unique(['id', 'company_id']);
            $table->foreign(['area_id', 'company_id'])->references(['id', 'company_id'])->on('areas')->restrictOnDelete();
            $table->index(['company_id', 'area_id']);
        });
        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('plate', 40);
            $table->string('name');
            $table->string('status', 30)->default('available');
            $table->timestamps();
            $table->unique(['company_id', 'plate']);
            $table->unique(['id', 'company_id']);
        });
        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id');
            $table->unsignedBigInteger('representative_id');
            $table->date('scheduled_for');
            $table->string('status', 30)->default('planned');
            $table->timestamp('checked_in_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->decimal('checkin_latitude', 10, 7)->nullable();
            $table->decimal('checkin_longitude', 10, 7)->nullable();
            $table->decimal('accuracy_m', 10, 2)->nullable();
            $table->decimal('distance_m', 12, 2)->nullable();
            $table->timestamp('location_captured_at')->nullable();
            $table->boolean('location_verified')->default(false);
            $table->string('outcome_note', 1000)->nullable();
            $table->timestamps();
            $table->unique(['id', 'company_id']);
            $table->foreign(['customer_id', 'company_id'])->references(['id', 'company_id'])->on('customers')->restrictOnDelete();
            $table->foreign(['company_id', 'representative_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $table->index(['company_id', 'scheduled_for', 'status']);
            $table->index(['company_id', 'representative_id', 'scheduled_for']);
        });
        Schema::create('audit_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->foreignId('actor_id')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('action', 80);
            $table->string('subject_type', 80);
            $table->unsignedBigInteger('subject_id');
            $table->json('metadata')->nullable();
            $table->timestamp('created_at');
            $table->index(['company_id', 'created_at']);
        });
    }

    public function down(): void
    {
        foreach (['audit_logs', 'visits', 'vehicles', 'customers', 'areas', 'company_user', 'companies'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
