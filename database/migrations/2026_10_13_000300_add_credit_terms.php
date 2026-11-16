<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $t) {
            $t->unsignedBigInteger('credit_limit_minor')->nullable();
        });
        Schema::table('orders', function (Blueprint $t) {
            $t->string('payment_terms')->default('credit');
            $t->date('due_date')->nullable();
            $t->unsignedBigInteger('initial_paid_minor')->default(0);
            $t->index(['company_id', 'due_date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $t) {
            $t->dropIndex(['company_id', 'due_date', 'status']);
            $t->dropColumn(['payment_terms', 'due_date', 'initial_paid_minor']);
        });
        Schema::table('customers', fn (Blueprint $t) => $t->dropColumn('credit_limit_minor'));
    }
};
