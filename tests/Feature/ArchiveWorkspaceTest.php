<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\ArchiveDepartment;
use App\Models\ArchiveVersion;
use App\Models\Company;
use App\Models\HandoverFile;
use App\Models\HandoverRequest;
use App\Models\User;
use App\Services\ArchiveAccess;
use App\Support\CompanyContext;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ArchiveWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $writer;

    private User $reviewer;

    private User $other;

    private ArchiveDepartment $department;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->company = Company::create(['name' => 'Synthetic archive', 'slug' => 'archive-test']);
        foreach (['manager' => 'manager', 'writer' => 'warehouse_keeper', 'reviewer' => 'accountant', 'other' => 'production'] as $property => $role) {
            $this->$property = User::factory()->create();
            $this->company->users()->attach($this->$property->id, ['role' => $role, 'is_active' => true, 'can_sell' => false, 'can_collect' => false]);
        }
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->department = ArchiveDepartment::create(['code' => 'warehouses', 'name' => 'Synthetic store archive']);
        $this->grant($this->writer, ['upload' => true, 'preview' => true, 'download' => true, 'print' => true]);
        $this->grant($this->reviewer, ['preview' => true, 'approve' => true, 'audit' => true]);
        $this->actor($this->writer);
    }

    private function actor(User $u): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->actingAs($u)->withSession(['company_id' => $this->company->id]);
    }

    private function grant(User $u, array $rights = [], string $level = 'internal'): void
    {
        $d = ['company_id' => $this->company->id, 'department_id' => $this->department->id, 'user_id' => $u->id, 'max_confidentiality' => $level, 'version' => 1, 'created_at' => now(), 'updated_at' => now()];
        foreach (ArchiveAccess::RIGHTS as $right) {
            $d['can_'.$right] = $right === 'view' || ($rights[$right] ?? false);
        }
        DB::table('archive_permissions')->updateOrInsert(['company_id' => $this->company->id, 'department_id' => $this->department->id, 'user_id' => $u->id], $d);
    }

    private function payload(): array
    {
        return ['department_id' => $this->department->id, 'title' => 'Synthetic signed receipt', 'document_type' => 'Receipt', 'confidentiality' => 'internal', 'document_date' => now()->toDateString(), 'reference' => 'AR-SYN-01', 'physical_reference' => 'Cabinet 1', 'notes' => 'Synthetic private archive record', 'version_note' => 'First synthetic upload', 'signed_copy' => true, 'document' => UploadedFile::fake()->create('receipt.pdf', 4, 'application/pdf')];
    }

    private function command(string $url, array $d = [], ?string $key = null)
    {
        return $this->postJson($url, $d + ['request_id' => $key ?? (string) Str::uuid()]);
    }

    private function create(): int
    {
        $this->actor($this->writer);

        return $this->command('/ops/archive/documents', $this->payload())->assertOk()->json('id');
    }

    private function approve(int $id): void
    {
        $this->actor($this->reviewer);
        $this->command('/ops/archive/documents/'.$id.'/decision', ['version' => 2, 'decision' => 'approved', 'note' => 'Independent synthetic review', 'acknowledge' => true])->assertOk();
    }

    private function permissions(User $u, array $rights = [], int $version = 0): array
    {
        $d = ['user_id' => $u->id, 'department_id' => $this->department->id, 'version' => $version, 'max_confidentiality' => 'internal', 'reason' => 'Synthetic permission update'];
        foreach (ArchiveAccess::RIGHTS as $right) {
            $d['can_'.$right] = $rights[$right] ?? false;
        }

        return $d;
    }

    public function test_upload_is_private_idempotent_and_not_approved_automatically(): void
    {
        $d = $this->payload();
        $key = (string) Str::uuid();
        $id = $this->command('/ops/archive/documents', $d, $key)->assertOk()->json('id');
        $this->command('/ops/archive/documents', $d, $key)->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('archive_documents', 1);
        $this->assertDatabaseCount('archive_versions', 1);
        $this->getJson('/ops/archive/documents/'.$id)->assertOk()->assertJsonPath('document.status', 'pending_review')->assertJsonMissingPath('versions.0.path')->assertJsonPath('versions.0.metadata_snapshot.title', $d['title']);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseCount('stock_movements', 0);
        $this->assertDatabaseHas('workspace_notifications', ['user_id' => $this->reviewer->id, 'archive_document_id' => $id]);
    }

    public function test_without_grant_there_is_no_archive_or_document_access(): void
    {
        $id = $this->create();
        $this->actor($this->other);
        $this->get('/archive')->assertForbidden();
        $this->getJson('/ops/archive')->assertForbidden();
        $this->getJson('/ops/archive/documents/'.$id)->assertNotFound();
        $this->get('/ops/archive/documents/'.$id.'/versions/1/download')->assertNotFound();
        $this->command('/ops/archive/documents', $this->payload())->assertForbidden();
    }

    public function test_preview_download_and_print_rights_are_separate_and_drafts_are_restricted(): void
    {
        $id = $this->create();
        $this->grant($this->other, ['preview' => true]);
        $this->actor($this->other);
        $this->get('/ops/archive/documents/'.$id.'/versions/1/preview')->assertForbidden();
        $this->approve($id);
        $this->actor($this->other);
        $this->get('/ops/archive/documents/'.$id.'/versions/1/preview')->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->get('/ops/archive/documents/'.$id.'/versions/1/download')->assertForbidden();
        $this->get('/ops/archive/documents/'.$id.'/versions/1/print')->assertForbidden();
        $this->getJson('/ops/archive/documents/'.$id.'/audit')->assertNotFound();
        $this->get('/ops/archive/export')->assertForbidden();
    }

    public function test_creator_cannot_approve_own_version_even_after_granting_approval(): void
    {
        $id = $this->create();
        $this->grant($this->writer, ['approve' => true]);
        $this->command('/ops/archive/documents/'.$id.'/decision', ['version' => 2, 'decision' => 'approved', 'note' => 'Self approval attempt', 'acknowledge' => true])->assertForbidden();
        $this->approve($id);
        $this->assertDatabaseHas('archive_versions', ['document_id' => $id, 'number' => 1, 'status' => 'approved', 'decided_by' => $this->reviewer->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive.approved']);
        $this->getJson('/ops/archive/documents/'.$id.'/audit')->assertOk()->assertJsonPath('events.data.0.actor_name', $this->reviewer->name);
    }

    public function test_new_version_preserves_file_metadata_and_approval_of_prior_version(): void
    {
        $id = $this->create();
        $this->approve($id);
        $this->actor($this->writer);
        $d = $this->payload();
        $d['title'] = 'Corrected synthetic title';
        $d['version'] = '3';
        $d['version_note'] = 'Corrected title and signed attachment';
        $this->command('/ops/archive/documents/'.$id.'/versions', $d)->assertOk();
        $this->assertDatabaseCount('archive_versions', 2);
        $this->assertCount(2, Storage::disk('local')->allFiles());
        $this->getJson('/ops/archive/documents/'.$id)->assertOk()->assertJsonPath('document.title', $d['title'])->assertJsonPath('document.status', 'pending_review')->assertJsonPath('versions.0.number', 2)->assertJsonPath('versions.1.status', 'approved')->assertJsonPath('versions.1.metadata_snapshot.title', 'Synthetic signed receipt');
    }

    public function test_stale_document_and_permission_versions_are_rejected(): void
    {
        $id = $this->create();
        $d = $this->payload();
        $d['version'] = '999';
        $this->command('/ops/archive/documents/'.$id.'/versions', $d)->assertConflict();
        $this->assertDatabaseCount('archive_versions', 1);
        $this->actor($this->manager);
        $d = $this->permissions($this->other, ['view' => true]);
        $this->command('/ops/archive/permissions', $d)->assertOk();
        $this->command('/ops/archive/permissions', $d)->assertConflict();
    }

    public function test_revocation_and_classification_apply_to_direct_file_urls(): void
    {
        $id = $this->create();
        $this->approve($id);
        $this->actor($this->manager);
        $this->command('/ops/archive/permissions', $this->permissions($this->writer, [], 1))->assertOk();
        $this->actor($this->writer);
        $this->getJson('/ops/archive/documents/'.$id)->assertNotFound();
        $this->get('/ops/archive/documents/'.$id.'/versions/1/download')->assertNotFound();
        $this->actor($this->manager);
        $d = $this->payload();
        $d['confidentiality'] = 'restricted';
        $private = $this->command('/ops/archive/documents', $d)->assertOk()->json('id');
        $this->actor($this->reviewer);
        $this->getJson('/ops/archive/documents/'.$private)->assertNotFound();
        $this->getJson('/ops/archive')->assertJsonPath('documents.total', 1);
    }

    public function test_file_integrity_is_checked_before_approval_and_read(): void
    {
        $id = $this->create();
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $v = ArchiveVersion::where('document_id', $id)->firstOrFail();
        Storage::disk('local')->put($v->path, 'Changed bytes');
        $this->actor($this->reviewer);
        $this->command('/ops/archive/documents/'.$id.'/decision', ['version' => 2, 'decision' => 'approved', 'note' => 'Must not approve changed file', 'acknowledge' => true])->assertConflict();
        $this->get('/ops/archive/documents/'.$id.'/versions/1/preview')->assertConflict();
    }

    public function test_bad_file_and_empty_metadata_do_not_leave_documents_or_files(): void
    {
        $d = $this->payload();
        $d['document'] = UploadedFile::fake()->createWithContent('unsafe.html', '<script>alert(1)</script>');
        $this->command('/ops/archive/documents', $d)->assertUnprocessable();
        $this->assertDatabaseCount('archive_documents', 0);
        $this->assertCount(0, Storage::disk('local')->allFiles());
        $id = $this->create();
        $d = $this->payload();
        $d['version'] = 2;
        $d['title'] = null;
        $this->command('/ops/archive/documents/'.$id.'/versions', $d)->assertUnprocessable();
        $this->assertDatabaseCount('archive_versions', 1);
    }

    public function test_only_manager_changes_access_and_no_inconsistent_or_viewer_write_grants(): void
    {
        $this->command('/ops/archive/permissions', $this->permissions($this->other, ['view' => true]))->assertForbidden();
        $this->actor($this->manager);
        $this->command('/ops/archive/permissions', $this->permissions($this->other, ['download' => true]))->assertUnprocessable();
        $this->company->users()->updateExistingPivot($this->other->id, ['role' => 'viewer']);
        $this->command('/ops/archive/permissions', $this->permissions($this->other, ['view' => true, 'upload' => true]))->assertUnprocessable();
        $this->assertDatabaseMissing('archive_permissions', ['user_id' => $this->other->id]);
    }

    public function test_other_company_cannot_use_document_department_or_permission_ids(): void
    {
        $id = $this->create();
        $other = Company::create(['name' => 'Other archive company', 'slug' => 'archive-other']);
        $other->users()->attach($this->manager->id, ['role' => 'manager', 'is_active' => true]);
        $this->actor($this->manager);
        $this->withSession(['company_id' => $other->id]);
        $this->getJson('/ops/archive')->assertOk()->assertJsonPath('documents.total', 0);
        $this->getJson('/ops/archive/documents/'.$id)->assertNotFound();
        $this->get('/ops/archive/documents/'.$id.'/versions/1/download')->assertNotFound();
        $this->command('/ops/archive/documents', $this->payload())->assertNotFound();
    }

    public function test_handover_import_keeps_original_blob_and_does_not_duplicate(): void
    {
        $this->actor($this->manager);
        $handover = HandoverRequest::create(['code' => 'HO-SYN-ARCHIVE', 'kind' => 'asset', 'sender_id' => $this->manager->id, 'recipient_id' => $this->writer->id, 'created_by' => $this->manager->id, 'sender_note' => 'Synthetic asset received', 'snapshot' => ['synthetic' => true], 'status' => 'accepted']);
        $path = 'handovers/synthetic.pdf';
        Storage::disk('local')->put($path, '%PDF-1.4 synthetic receipt');
        $f = HandoverFile::create(['handover_id' => $handover->id, 'user_id' => $this->writer->id, 'original_name' => 'signed.pdf', 'path' => $path, 'mime' => 'application/pdf', 'size' => strlen('%PDF-1.4 synthetic receipt'), 'sha256' => hash('sha256', '%PDF-1.4 synthetic receipt'), 'created_at' => now()]);
        $id = $this->command('/ops/archive/imports', ['file_id' => $f->id, 'note' => 'Index old receipt'])->assertOk()->json('id');
        $this->command('/ops/archive/imports', ['file_id' => $f->id, 'note' => 'Repeated indexing'])->assertOk()->assertJsonPath('id', $id);
        $this->assertDatabaseCount('archive_documents', 1);
        $this->assertDatabaseCount('archive_versions', 1);
        $this->assertCount(1, Storage::disk('local')->allFiles());
        $this->assertDatabaseHas('archive_versions', ['document_id' => $id, 'path' => $path, 'status' => 'pending_review']);
        $this->assertDatabaseHas('handover_requests', ['id' => $handover->id, 'status' => 'accepted']);
    }

    public function test_export_is_scoped_audited_and_protects_spreadsheet_formula_cells(): void
    {
        $d = $this->payload();
        $d['title'] = '=HYPERLINK("bad")';
        $this->command('/ops/archive/documents', $d)->assertOk();
        $this->grant($this->writer, ['export' => true]);
        $csv = $this->get('/ops/archive/export')->assertOk()->streamedContent();
        $this->assertStringContainsString("'=HYPERLINK", $csv);
        $this->assertDatabaseHas('audit_logs', ['action' => 'archive.export_requested']);
    }

    public function test_sections_follow_explicit_grant_and_suspension_is_respected(): void
    {
        $this->actor($this->other);
        $this->get('/dashboard')->assertRedirect('/production');
        $this->get('/archive')->assertForbidden();
        $this->grant($this->other);
        $this->get('/archive')->assertOk()->assertSee('archive.js');
        $this->company->users()->updateExistingPivot($this->other->id, ['is_active' => false]);
        $this->get('/archive')->assertForbidden();
    }

    public function test_read_only_device_token_cannot_upload_or_approve_archive_records(): void
    {
        $id = $this->create();
        Sanctum::actingAs($this->writer, ['operations:read']);
        $headers = ['X-Company-ID' => $this->company->id];
        $this->getJson('/api/v1/archive', $headers)->assertOk()->assertJsonPath('documents.total', 1);
        $this->postJson('/api/v1/archive/documents', $this->payload() + ['request_id' => (string) Str::uuid()], $headers)->assertForbidden();
        $this->postJson('/api/v1/archive/documents/'.$id.'/decision', ['request_id' => (string) Str::uuid()], $headers)->assertForbidden();
    }

    public function test_department_code_is_stable_and_inactive_department_hides_user_access(): void
    {
        $this->actor($this->manager);
        $data = ['id' => $this->department->id, 'code' => 'changed-code', 'name' => 'Renamed archive', 'description' => 'Synthetic description', 'is_active' => true, 'version' => 1, 'reason' => 'Synthetic update'];
        $this->command('/ops/archive/departments', $data)->assertUnprocessable();
        $data['code'] = 'warehouses';
        $data['is_active'] = false;
        $this->command('/ops/archive/departments', $data)->assertOk();
        $this->actor($this->writer);
        $this->get('/archive')->assertForbidden();
        $this->command('/ops/archive/documents', $this->payload())->assertForbidden();
        $this->actor($this->manager);
        $this->getJson('/ops/archive')->assertOk()->assertJsonPath('departments.0.is_active', false);
    }

    public function test_role_change_to_viewer_removes_reviewer_responsibility_even_if_old_grant_remains(): void
    {
        $id = $this->create();
        $this->company->users()->updateExistingPivot($this->reviewer->id, ['role' => 'viewer']);
        $this->actor($this->reviewer);
        $response = $this->getJson('/ops/archive/documents/'.$id)->assertOk()->assertJsonPath('can_approve', false);
        $this->assertNotContains($this->reviewer->id, array_column($response->json('reviewers'), 'id'));
        $this->command('/ops/archive/documents/'.$id.'/decision', ['version' => 2, 'decision' => 'approved', 'note' => 'Viewer cannot approve', 'acknowledge' => true])->assertForbidden();
    }

    public function test_revoked_creator_does_not_receive_new_document_details_in_notifications(): void
    {
        $id = $this->create();
        DB::table('archive_permissions')->where('user_id', $this->writer->id)->delete();
        $this->approve($id);
        $this->assertDatabaseMissing('workspace_notifications', ['user_id' => $this->writer->id, 'event' => 'archive.decided']);
    }
}
