<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_changes', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('customer_id')->nullable();
            $t->foreignId('submitted_by')->constrained('users')->restrictOnDelete();
            $t->string('kind', 30);
            $t->string('status', 20)->default('pending');
            $t->json('payload');
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->text('review_note')->nullable();
            $t->timestamp('reviewed_at')->nullable();
            $t->timestamps();
            $t->foreign(['customer_id', 'company_id'])->references(['id', 'company_id'])->on('customers')->restrictOnDelete();
            $t->index(['company_id', 'status', 'submitted_by']);
        });
        Schema::create('representative_vehicle_assignments', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('representative_id');
            $t->unsignedBigInteger('vehicle_id');
            $t->unsignedBigInteger('active_representative_id')->nullable();
            $t->unsignedBigInteger('active_vehicle_id')->nullable();
            $t->foreignId('assigned_by')->constrained('users')->restrictOnDelete();
            $t->foreignId('returned_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('assigned_at');
            $t->timestamp('returned_at')->nullable();
            $t->text('assignment_note');
            $t->text('return_note')->nullable();
            $t->unique(['company_id', 'active_representative_id'], 'rep_vehicle_active_rep_unique');
            $t->unique(['company_id', 'active_vehicle_id'], 'rep_vehicle_active_vehicle_unique');
            $t->foreign(['company_id', 'representative_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->foreign(['vehicle_id', 'company_id'])->references(['id', 'company_id'])->on('vehicles')->restrictOnDelete();
        });
        Schema::table('company_user', fn (Blueprint $t) => $t->unsignedInteger('operations_version')->default(1));
    }

    public function down(): void
    {
        Schema::table('company_user', fn (Blueprint $t) => $t->dropColumn('operations_version'));
        Schema::dropIfExists('representative_vehicle_assignments');
        Schema::dropIfExists('customer_changes');
    }
};
