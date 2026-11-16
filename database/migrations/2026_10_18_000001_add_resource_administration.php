<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('company_user', function (Blueprint $table) {
            $table->string('phone', 30)->nullable();
            $table->string('employee_code', 40)->nullable();
            $table->string('area_access', 20)->default('all');
            $table->boolean('can_sell')->default(true);
            $table->boolean('can_collect')->default(true);
            $table->string('notes', 1000)->nullable();
            $table->unique(['company_id', 'employee_code']);
        });
        Schema::create('representative_areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('company_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('area_id');
            $table->unique(['company_id', 'user_id', 'area_id']);
            $table->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $table->foreign(['area_id', 'company_id'])->references(['id', 'company_id'])->on('areas')->restrictOnDelete();
        });
        Schema::create('drivers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->string('name', 150);
            $table->string('phone', 30)->nullable();
            $table->string('employee_code', 40);
            $table->string('license_number', 80)->nullable();
            $table->date('license_expires_on')->nullable();
            $table->string('status', 20)->default('active');
            $table->string('notes', 1000)->nullable();
            $table->timestamps();
            $table->unique(['company_id', 'employee_code']);
            $table->unique(['id', 'company_id']);
        });
        Schema::table('vehicles', function (Blueprint $table) {
            // Existing records remain unknown until reviewed; never infer a van from its illustration.
            $table->string('vehicle_type', 30)->default('unspecified');
            $table->string('make', 80)->nullable();
            $table->string('model', 80)->nullable();
            $table->unsignedSmallInteger('model_year')->nullable();
            $table->string('vin', 80)->nullable();
            $table->string('ownership', 20)->default('owned');
            $table->unsignedInteger('payload_kg')->nullable();
            $table->decimal('volume_m3', 10, 2)->nullable();
            $table->unsignedInteger('odometer_km')->nullable();
            $table->date('registration_expires_on')->nullable();
            $table->date('insurance_expires_on')->nullable();
            $table->string('notes', 1000)->nullable();
        });
        Schema::create('vehicle_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('vehicle_id');
            $table->unsignedBigInteger('driver_id');
            $table->unsignedBigInteger('active_vehicle_id')->nullable();
            $table->unsignedBigInteger('active_driver_id')->nullable();
            $table->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $table->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamp('returned_at')->nullable();
            $table->string('handover_note', 500);
            $table->string('return_note', 500)->nullable();
            $table->unique(['company_id', 'active_vehicle_id']);
            $table->unique(['company_id', 'active_driver_id']);
            $table->foreign(['vehicle_id', 'company_id'])->references(['id', 'company_id'])->on('vehicles')->restrictOnDelete();
            $table->foreign(['driver_id', 'company_id'])->references(['id', 'company_id'])->on('drivers')->restrictOnDelete();
        });
        Schema::table('trips', function (Blueprint $table) {
            $table->unsignedBigInteger('driver_id')->nullable();
            $table->foreign(['driver_id', 'company_id'])->references(['id', 'company_id'])->on('drivers')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('trips', function (Blueprint $table) {
            $table->dropForeign(['driver_id', 'company_id']);
            $table->dropColumn('driver_id');
        });
        Schema::dropIfExists('vehicle_assignments');
        Schema::table('vehicles', fn (Blueprint $table) => $table->dropColumn(['vehicle_type', 'make', 'model', 'model_year', 'vin', 'ownership', 'payload_kg', 'volume_m3', 'odometer_km', 'registration_expires_on', 'insurance_expires_on', 'notes']));
        Schema::dropIfExists('drivers');
        Schema::dropIfExists('representative_areas');
        Schema::table('company_user', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'employee_code']);
            $table->dropColumn(['phone', 'employee_code', 'area_access', 'can_sell', 'can_collect', 'notes']);
        });
    }
};
