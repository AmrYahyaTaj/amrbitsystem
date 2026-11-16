<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('temporary_delegations', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->string('code', 40);
            $t->unsignedBigInteger('principal_id');
            $t->unsignedBigInteger('delegate_id');
            $t->unsignedBigInteger('created_by');
            $t->string('principal_role', 40);
            $t->string('delegate_role', 40);
            $t->string('domain', 40);
            $t->unsignedBigInteger('warehouse_id')->nullable();
            $t->unsignedBigInteger('department_id')->nullable();
            $t->string('max_confidentiality', 20)->nullable();
            $t->timestamp('starts_at');
            $t->timestamp('ends_at');
            $t->text('reason');
            $t->string('status', 20)->default('pending');
            $t->unsignedInteger('version')->default(1);
            $t->timestamp('responded_at')->nullable();
            $t->text('response_note')->nullable();
            $t->unsignedBigInteger('revoked_by')->nullable();
            $t->timestamp('revoked_at')->nullable();
            $t->text('revocation_note')->nullable();
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            $t->index(['company_id', 'delegate_id', 'domain', 'status'], 'delegation_recipient_status');
            foreach (['principal_id', 'delegate_id', 'created_by', 'revoked_by'] as $column) {
                $t->foreign(['company_id', $column])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            }
            $t->foreign(['warehouse_id', 'company_id'])->references(['id', 'company_id'])->on('warehouses')->restrictOnDelete();
            $t->foreign(['department_id', 'company_id'])->references(['id', 'company_id'])->on('archive_departments')->restrictOnDelete();
        });
        Schema::table('quality_inspections', function (Blueprint $t) {
            foreach (['inspected_delegation_id', 'decided_delegation_id'] as $column) {
                $t->unsignedBigInteger($column)->nullable();
                $t->foreign([$column, 'company_id'])->references(['id', 'company_id'])->on('temporary_delegations')->restrictOnDelete();
            }
        });
        Schema::table('archive_versions', function (Blueprint $t) {
            $t->unsignedBigInteger('decided_delegation_id')->nullable();
            $t->foreign(['decided_delegation_id', 'company_id'])->references(['id', 'company_id'])->on('temporary_delegations')->restrictOnDelete();
        });
        Schema::table('workspace_notifications', function (Blueprint $t) {
            $t->unsignedBigInteger('delegation_id')->nullable();
            $t->foreign(['delegation_id', 'company_id'])->references(['id', 'company_id'])->on('temporary_delegations')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('temporary_delegations')->exists()) {
            throw new RuntimeException('Delegation history exists; use a verified backup instead of deleting it.');
        }
        foreach (['quality_inspections' => ['inspected_delegation_id', 'decided_delegation_id'], 'archive_versions' => ['decided_delegation_id'], 'workspace_notifications' => ['delegation_id']] as $table => $columns) {
            Schema::table($table, function (Blueprint $t) use ($columns) {
                foreach ($columns as $column) {
                    $t->dropForeign([$column, 'company_id']);
                    $t->dropColumn($column);
                }
            });
        }
        Schema::dropIfExists('temporary_delegations');
    }
};
