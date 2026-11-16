<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debt_followups', function (Blueprint $t) {
            $t->id();
            $t->foreignId('company_id')->constrained()->restrictOnDelete();
            $t->unsignedBigInteger('order_id');
            $t->foreignId('actor_id')->constrained('users')->restrictOnDelete();
            $t->string('outcome');
            $t->string('note', 1000);
            $t->date('next_followup_date');
            $t->unsignedBigInteger('promised_minor')->nullable();
            $t->timestamp('created_at');
            $t->foreign(['order_id', 'company_id'])->references(['id', 'company_id'])->on('orders')->restrictOnDelete();
            $t->index(['company_id', 'next_followup_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debt_followups');
    }
};
