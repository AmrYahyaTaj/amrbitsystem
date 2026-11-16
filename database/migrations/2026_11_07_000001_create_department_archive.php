<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('handover_files', fn (Blueprint $t) => $t->unique(['id', 'company_id'], 'archive_handover_file_company_unique'));
        Schema::create('archive_departments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->string('code', 40);
            $t->string('name', 150);
            $t->text('description')->nullable();
            $t->boolean('is_active')->default(true);
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            $t->foreign('company_id')->references('id')->on('companies')->restrictOnDelete();
        });
        Schema::create('archive_permissions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('department_id');
            $t->unsignedBigInteger('user_id');
            foreach (['view', 'preview', 'upload', 'download', 'print', 'approve', 'audit', 'export'] as $right) {
                $t->boolean('can_'.$right)->default(false);
            }
            $t->string('max_confidentiality', 20)->default('internal');
            $t->unsignedInteger('version')->default(1);
            $t->timestamps();
            $t->unique(['company_id', 'department_id', 'user_id'], 'archive_permission_unique');
            $t->foreign(['department_id', 'company_id'])->references(['id', 'company_id'])->on('archive_departments')->restrictOnDelete();
            $t->foreign(['company_id', 'user_id'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
        });
        Schema::create('archive_documents', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('department_id');
            $t->string('code', 40);
            $t->string('title', 200);
            $t->string('document_type', 80);
            $t->string('confidentiality', 20)->default('internal');
            $t->string('reference', 150)->nullable();
            $t->date('document_date');
            $t->date('expires_on')->nullable();
            $t->date('retain_until')->nullable();
            $t->string('physical_reference', 200)->nullable();
            $t->text('notes');
            $t->unsignedBigInteger('created_by');
            $t->unsignedBigInteger('source_handover_file_id')->nullable();
            $t->unsignedInteger('current_version')->default(0);
            $t->unsignedInteger('version')->default(1);
            $t->string('status', 30)->default('pending_review');
            $t->timestamps();
            $t->unique(['id', 'company_id']);
            $t->unique(['company_id', 'code']);
            $t->unique(['company_id', 'source_handover_file_id'], 'archive_source_file_unique');
            $t->index(['company_id', 'department_id', 'status']);
            $t->foreign(['department_id', 'company_id'])->references(['id', 'company_id'])->on('archive_departments')->restrictOnDelete();
            $t->foreign(['company_id', 'created_by'])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            $t->foreign(['source_handover_file_id', 'company_id'])->references(['id', 'company_id'])->on('handover_files')->restrictOnDelete();
        });
        Schema::create('archive_versions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('company_id');
            $t->unsignedBigInteger('document_id');
            $t->unsignedInteger('number');
            $t->unsignedBigInteger('uploaded_by');
            $t->string('original_name', 200);
            $t->string('path', 500);
            $t->string('mime', 100);
            $t->unsignedBigInteger('size');
            $t->string('sha256', 64);
            $t->text('note');
            $t->boolean('signed_copy')->default(false);
            $t->json('metadata_snapshot')->nullable();
            $t->string('status', 30)->default('pending_review');
            $t->unsignedBigInteger('decided_by')->nullable();
            $t->text('decision_note')->nullable();
            $t->timestamp('decided_at')->nullable();
            $t->timestamp('created_at');
            $t->unique(['company_id', 'document_id', 'number'], 'archive_version_number_unique');
            $t->foreign(['document_id', 'company_id'])->references(['id', 'company_id'])->on('archive_documents')->restrictOnDelete();
            foreach (['uploaded_by', 'decided_by'] as $column) {
                $t->foreign(['company_id', $column])->references(['company_id', 'user_id'])->on('company_user')->restrictOnDelete();
            }
        });
        Schema::table('workspace_notifications', function (Blueprint $t) {
            $t->unsignedBigInteger('archive_document_id')->nullable();
            $t->foreign(['archive_document_id', 'company_id'], 'notification_archive_company_fk')->references(['id', 'company_id'])->on('archive_documents')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        if (DB::table('archive_documents')->exists()) {
            throw new RuntimeException('Archive history exists; use a verified backup instead of deleting it.');
        }
        Schema::table('workspace_notifications', function (Blueprint $t) {
            $t->dropForeign('notification_archive_company_fk');
            $t->dropColumn('archive_document_id');
        });
        Schema::dropIfExists('archive_versions');
        Schema::dropIfExists('archive_documents');
        Schema::dropIfExists('archive_permissions');
        Schema::dropIfExists('archive_departments');
        Schema::table('handover_files', fn (Blueprint $t) => $t->dropUnique('archive_handover_file_company_unique'));
    }
};
