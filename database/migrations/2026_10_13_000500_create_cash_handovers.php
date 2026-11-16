<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cash_handovers', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->string('code');
            $t->unsignedBigInteger('collector_id');
            $t->foreignId('requested_by')->constrained('users')->restrictOnDelete();
            $t->unsignedBigInteger('amount_minor');
            $t->string('status')->default('pending');
            $t->string('reference', 100);
            $t->string('note', 500)->nullable();
            $t->foreignId('reviewed_by')->nullable()->constrained('users')->restrictOnDelete();
            $t->timestamp('reviewed_at')->nullable();
            $t->string('review_note', 500)->nullable();
            $t->timestamps();
            $t->foreign(['company_id', 'collector_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->unique(['company_id', 'code']);
            $t->index(['company_id', 'collector_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cash_handovers');
    }
};
