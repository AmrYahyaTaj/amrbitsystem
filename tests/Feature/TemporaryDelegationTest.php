<?php

namespace Tests\Feature;

use App\Enums\CompanyRole;
use App\Models\ArchiveDepartment;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\QualityInspection;
use App\Models\StockLot;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\ArchiveAccess;
use App\Support\CompanyContext;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class TemporaryDelegationTest extends TestCase
{
    use RefreshDatabase;

    private Company $company;

    private User $manager;

    private User $producer;

    private User $inspector;

    private User $inspectAlt;

    private User $approver;

    private User $approveAlt;

    private User $archOwner;

    private User $archAlt;

    private User $writer;

    private User $viewer;

    private Warehouse $site;

    private ArchiveDepartment $department;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->company = Company::create(['name' => 'Synthetic delegation company', 'slug' => 'delegation-test']);
        foreach (['manager' => 'manager', 'producer' => 'production', 'inspector' => 'quality_inspector', 'inspectAlt' => 'quality_inspector', 'approver' => 'quality_manager', 'approveAlt' => 'quality_manager', 'archOwner' => 'accountant', 'archAlt' => 'warehouse_keeper', 'writer' => 'representative', 'viewer' => 'viewer'] as $key => $role) {
            $this->$key = User::factory()->create();
            $this->company->users()->attach($this->$key->id, ['role' => $role, 'is_active' => true, 'can_sell' => false, 'can_collect' => false]);
        }
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->site = Warehouse::create(['name' => 'Delegation site', 'type' => 'production', 'code' => 'DEL-PROD', 'custodian_id' => $this->producer->id]);
        $this->department = ArchiveDepartment::create(['code' => 'quality', 'name' => 'Quality archive']);
        $this->product = Product::create(['name' => 'Synthetic paper', 'sku' => 'DEL-PAPER', 'unit' => 'box', 'price_minor' => 100]);
        foreach ([$this->inspector, $this->approver] as $user) {
            DB::table('quality_site_access')->insert(['company_id' => $this->company->id, 'user_id' => $user->id, 'warehouse_id' => $this->site->id, 'created_at' => now(), 'updated_at' => now()]);
        }
        $this->grantArchive($this->archOwner, ['view', 'preview', 'approve'], 'confidential');
        $this->grantArchive($this->writer, ['view', 'upload', 'preview'], 'confidential');
        $this->actor($this->manager);
    }

    private function actor(User $u): void
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $this->actingAs($u)->withSession(['company_id' => $this->company->id]);
    }

    private function command(string $url, array $data = [], ?string $key = null)
    {
        return $this->postJson($url, $data + ['request_id' => $key ?? (string) Str::uuid()]);
    }

    private function grantArchive(User $u, array $rights, string $level = 'internal'): void
    {
        $d = ['company_id' => $this->company->id, 'department_id' => $this->department->id, 'user_id' => $u->id, 'max_confidentiality' => $level, 'created_at' => now(), 'updated_at' => now()];
        foreach (ArchiveAccess::RIGHTS as $r) {
            $d['can_'.$r] = in_array($r, $rights, true);
        }DB::table('archive_permissions')->updateOrInsert(['company_id' => $this->company->id, 'department_id' => $this->department->id, 'user_id' => $u->id], $d);
    }

    private function payload(string $domain = 'quality_inspect'): array
    {
        $data = ['domain' => $domain, 'starts_at' => now()->timezone('Asia/Riyadh')->format('Y-m-d\TH:i'), 'ends_at' => now()->addDay()->timezone('Asia/Riyadh')->format('Y-m-d\TH:i'), 'reason' => 'Synthetic absence and temporary coverage'];
        if ($domain === 'archive_review') {
            return $data + ['principal_id' => $this->archOwner->id, 'delegate_id' => $this->archAlt->id, 'department_id' => $this->department->id, 'max_confidentiality' => 'confidential'];
        }

        return $data + ['principal_id' => $domain === 'quality_inspect' ? $this->inspector->id : $this->approver->id, 'delegate_id' => $domain === 'quality_inspect' ? $this->inspectAlt->id : $this->approveAlt->id, 'warehouse_id' => $this->site->id];
    }

    private function delegation(string $domain = 'quality_inspect', bool $accept = true, array $override = []): int
    {
        $this->actor($this->manager);
        $d = array_replace($this->payload($domain), $override);
        $id = $this->command('/ops/delegations', $d)->assertOk()->json('id');
        if ($accept) {
            $user = User::findOrFail($d['delegate_id']);
            $this->actor($user);
            $this->command('/ops/delegations/'.$id.'/respond', ['version' => 1, 'decision' => 'accepted', 'note' => 'Synthetic acceptance of scope and term', 'acknowledge' => true])->assertOk();
        }

        return $id;
    }

    private function inspection(): int
    {
        app(CompanyContext::class)->set($this->company, CompanyRole::Manager);
        $order = ProductionOrder::create(['code' => 'P-'.Str::random(7), 'warehouse_id' => $this->site->id, 'product_id' => $this->product->id, 'operator_id' => $this->producer->id, 'created_by' => $this->producer->id, 'completed_by' => $this->producer->id, 'line' => 'Synthetic', 'shift' => 'Day', 'scheduled_on' => now()->toDateString(), 'planned_quantity' => 10, 'good_quantity' => 10, 'status' => 'completed', 'notes' => 'Synthetic production']);
        $lot = StockLot::create(['code' => 'LOT-'.Str::random(8), 'product_id' => $this->product->id, 'origin_warehouse_id' => $this->site->id, 'production_order_id' => $order->id, 'source' => 'production', 'status' => 'quarantine']);

        return QualityInspection::create(['code' => 'QA-'.Str::random(8), 'lot_id' => $lot->id, 'active_lot_id' => $lot->id, 'warehouse_id' => $this->site->id, 'requested_by' => $this->producer->id, 'lot_version' => 1, 'request_note' => 'Synthetic quality check', 'due_at' => now()->addDay()])->id;
    }

    private function inspectPayload(): array
    {
        return ['version' => '1', 'sample_size' => 3, 'checks' => [['name' => 'Synthetic specification', 'result' => 'pass', 'observation' => 'Synthetic sample within limits']], 'recommendation' => 'released', 'inspection_note' => 'Synthetic independent check'];
    }

    private function archiveDocument(string $level = 'internal'): int
    {
        $this->actor($this->writer);

        return $this->command('/ops/archive/documents', ['department_id' => $this->department->id, 'title' => 'Synthetic delegated review', 'document_type' => 'Receipt', 'confidentiality' => $level, 'document_date' => now()->toDateString(), 'notes' => 'Synthetic archive document', 'version_note' => 'First sample version', 'signed_copy' => false, 'document' => UploadedFile::fake()->create('receipt.pdf', 4, 'application/pdf')])->assertOk()->json('id');
    }

    public function test_pending_acceptance_is_required_and_actor_and_delegation_are_recorded(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation('quality_inspect', false);
        $this->actor($this->inspectAlt);
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->command('/ops/delegations/'.$id.'/respond', ['version' => '1', 'decision' => 'accepted', 'note' => 'Checked scope and dates', 'acknowledge' => true])->assertOk();
        $this->getJson('/ops/quality/'.$qa)->assertOk()->assertJsonPath('inspection.can_inspect', true);
        $this->command('/ops/quality/'.$qa.'/inspect', $this->inspectPayload())->assertOk();
        $this->assertDatabaseHas('quality_inspections', ['id' => $qa, 'inspected_by' => $this->inspectAlt->id, 'inspected_delegation_id' => $id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'quality.inspected', 'actor_id' => $this->inspectAlt->id]);
        $this->getJson('/ops/quality/'.$qa)->assertJsonPath('inspection.inspection_authority.principal.id', $this->inspector->id);
    }

    public function test_quality_approval_can_be_delegated_but_only_inside_the_scoped_site(): void
    {
        $qa = $this->inspection();
        $this->actor($this->inspector);
        $this->command('/ops/quality/'.$qa.'/inspect', $this->inspectPayload())->assertOk();
        $id = $this->delegation('quality_approve');
        $this->command('/ops/quality/'.$qa.'/decide', ['version' => '2', 'decision' => 'released', 'decision_note' => 'Independent delegated review', 'acknowledge' => true])->assertOk();
        $this->assertDatabaseHas('quality_inspections', ['id' => $qa, 'decided_by' => $this->approveAlt->id, 'decided_delegation_id' => $id]);
        $this->getJson('/ops/customers/1')->assertForbidden();
        $this->getJson('/ops/data')->assertForbidden();
    }

    public function test_expiry_uses_current_time_without_a_background_job(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation();
        $this->travel(2)->days();
        $this->actor($this->inspectAlt);
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->getJson('/ops/delegations/'.$id)->assertOk()->assertJsonPath('delegation.effective_status', 'expired');
        $this->command('/ops/delegations/'.$id.'/respond', ['version' => 1, 'decision' => 'accepted', 'note' => 'Expired acceptance', 'acknowledge' => true])->assertConflict();
    }

    public function test_scheduled_accepted_delegation_has_no_rights_before_its_start(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation('quality_inspect', true, ['starts_at' => now()->addDay()->timezone('Asia/Riyadh')->format('Y-m-d\TH:i'), 'ends_at' => now()->addDays(3)->timezone('Asia/Riyadh')->format('Y-m-d\TH:i')]);
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->getJson('/ops/delegations/'.$id)->assertJsonPath('delegation.effective_status', 'scheduled');
        $this->travel(2)->days();
        $this->getJson('/ops/quality/'.$qa)->assertOk();
    }

    public function test_revocation_blocks_visibility_actions_and_successful_action_replay(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation();
        $key = (string) Str::uuid();
        $data = $this->inspectPayload();
        $this->command('/ops/quality/'.$qa.'/inspect', $data, $key)->assertOk();
        $this->actor($this->manager);
        $this->command('/ops/delegations/'.$id.'/revoke', ['version' => 2, 'note' => 'End synthetic substitute coverage'])->assertOk();
        $this->actor($this->inspectAlt);
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->command('/ops/quality/'.$qa.'/inspect', $data, $key)->assertNotFound();
    }

    public function test_source_scope_removal_and_participant_suspension_stop_effective_rights(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation();
        DB::table('quality_site_access')->where('user_id', $this->inspector->id)->delete();
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->getJson('/ops/delegations/'.$id)->assertJsonPath('delegation.effective_status', 'invalid');
        $this->company->users()->updateExistingPivot($this->inspectAlt->id, ['is_active' => false]);
        $this->get('/delegations')->assertForbidden();
    }

    public function test_overlaps_self_delegation_wrong_roles_and_excessive_terms_are_rejected(): void
    {
        $this->delegation('quality_inspect', false);
        $this->actor($this->manager);
        $this->command('/ops/delegations', $this->payload())->assertConflict();
        $d = $this->payload();
        $d['delegate_id'] = $d['principal_id'];
        $this->command('/ops/delegations', $d)->assertUnprocessable();
        $d = $this->payload();
        $d['delegate_id'] = $this->viewer->id;
        $this->command('/ops/delegations', $d)->assertUnprocessable();
        $d = $this->payload();
        $d['ends_at'] = now()->addDays(31)->timezone('Asia/Riyadh')->format('Y-m-d\TH:i');
        $this->command('/ops/delegations', $d)->assertUnprocessable();
    }

    public function test_only_manager_creates_and_only_named_delegate_accepts(): void
    {
        $id = $this->delegation('quality_inspect', false);
        $this->command('/ops/delegations/'.$id.'/respond', ['version' => 1, 'decision' => 'accepted', 'note' => 'Manager must not impersonate', 'acknowledge' => true])->assertForbidden();
        $this->actor($this->viewer);
        $this->getJson('/ops/delegations/'.$id)->assertNotFound();
        $this->command('/ops/delegations', $this->payload())->assertForbidden();
    }

    public function test_rejection_needs_no_false_acceptance_and_does_not_grant_access(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation('quality_inspect', false);
        $this->actor($this->inspectAlt);
        $this->command('/ops/delegations/'.$id.'/respond', ['version' => 1, 'decision' => 'rejected', 'note' => 'Cannot cover this interval', 'acknowledge' => false])->assertOk();
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->assertDatabaseHas('temporary_delegations', ['id' => $id, 'status' => 'rejected']);
    }

    public function test_archive_delegation_records_authority_and_does_not_add_download_export_or_upload(): void
    {
        $doc = $this->archiveDocument();
        $id = $this->delegation('archive_review');
        $this->get('/archive')->assertOk();
        $this->getJson('/ops/archive/documents/'.$doc)->assertOk()->assertJsonPath('can_approve', true);
        $this->get('/ops/archive/documents/'.$doc.'/versions/1/preview')->assertOk();
        $this->get('/ops/archive/documents/'.$doc.'/versions/1/download')->assertForbidden();
        $this->get('/ops/archive/export')->assertForbidden();
        $this->command('/ops/archive/documents/'.$doc.'/decision', ['version' => '2', 'decision' => 'approved', 'note' => 'Independent substitute review', 'acknowledge' => true])->assertOk();
        $this->assertDatabaseHas('archive_versions', ['document_id' => $doc, 'decided_by' => $this->archAlt->id, 'decided_delegation_id' => $id]);
        $this->getJson('/ops/archive/documents/'.$doc)->assertJsonPath('versions.0.decision_authority.principal.id', $this->archOwner->id);
    }

    public function test_archive_confidentiality_and_each_action_keep_their_own_limits(): void
    {
        $doc = $this->archiveDocument('confidential');
        $this->grantArchive($this->archAlt, ['view', 'download'], 'internal');
        $this->delegation('archive_review');
        $this->getJson('/ops/archive/documents/'.$doc)->assertOk();
        $this->get('/ops/archive/documents/'.$doc.'/versions/1/preview')->assertOk();
        $this->get('/ops/archive/documents/'.$doc.'/versions/1/download')->assertForbidden();
    }

    public function test_archive_principal_cannot_use_delegate_to_approve_principals_own_document(): void
    {
        $doc = $this->archiveDocument();
        DB::table('archive_documents')->where('id', $doc)->update(['created_by' => $this->archOwner->id]);
        $this->delegation('archive_review');
        $this->getJson('/ops/archive/documents/'.$doc)->assertJsonPath('can_approve', false);
        $this->command('/ops/archive/documents/'.$doc.'/decision', ['version' => 2, 'decision' => 'approved', 'note' => 'Blocked delegated self approval', 'acknowledge' => true])->assertForbidden();
    }

    public function test_quality_principal_involved_in_production_cannot_delegate_away_the_conflict(): void
    {
        $qa = $this->inspection();
        $this->actor($this->inspector);
        $this->command('/ops/quality/'.$qa.'/inspect', $this->inspectPayload())->assertOk();
        DB::table('production_orders')->update(['completed_by' => $this->manager->id]);
        $this->delegation('quality_approve', true, ['principal_id' => $this->manager->id]);
        $this->getJson('/ops/quality/'.$qa)->assertOk()->assertJsonPath('inspection.can_decide', false);
        $this->command('/ops/quality/'.$qa.'/decide', ['version' => 2, 'decision' => 'released', 'decision_note' => 'Conflict cannot be delegated away', 'acknowledge' => true])->assertForbidden();
    }

    public function test_cross_company_ids_and_read_only_tokens_cannot_create_or_accept(): void
    {
        $id = $this->delegation('quality_inspect', false);
        $other = Company::create(['name' => 'Other tenant', 'slug' => 'other-delegation']);
        $other->users()->attach($this->manager->id, ['role' => 'manager', 'is_active' => true]);
        $this->actor($this->manager);
        $this->withSession(['company_id' => $other->id]);
        $this->getJson('/ops/delegations/'.$id)->assertNotFound();
        $this->command('/ops/delegations', $this->payload())->assertUnprocessable();
        $this->actor($this->inspectAlt);
        Sanctum::actingAs($this->inspectAlt, ['operations:read']);
        $headers = ['X-Company-ID' => $this->company->id];
        $this->getJson('/api/v1/delegations/'.$id, $headers)->assertOk();
        $this->postJson('/api/v1/delegations/'.$id.'/respond', ['request_id' => Str::uuid(), 'version' => 1, 'decision' => 'accepted', 'note' => 'Token lacks write', 'acknowledge' => true], $headers)->assertForbidden();
    }

    public function test_delegation_cannot_be_redelegated_and_role_changes_remove_authority(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation();
        $this->actor($this->manager);
        $this->command('/ops/delegations', array_replace($this->payload(), ['principal_id' => $this->inspectAlt->id, 'delegate_id' => $this->inspector->id]))->assertUnprocessable();
        $this->company->users()->updateExistingPivot($this->inspectAlt->id, ['role' => 'quality_manager']);
        $this->actor($this->inspectAlt);
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->getJson('/ops/delegations/'.$id)->assertJsonPath('delegation.effective_status', 'invalid');
    }

    public function test_archive_source_permission_revocation_removes_inherited_preview_and_approval(): void
    {
        $doc = $this->archiveDocument();
        $id = $this->delegation('archive_review');
        DB::table('archive_permissions')->where('user_id', $this->archOwner->id)->delete();
        $this->get('/archive')->assertForbidden();
        $this->get('/ops/archive/documents/'.$doc.'/versions/1/preview')->assertNotFound();
        $this->command('/ops/archive/documents/'.$doc.'/decision', ['version' => 2, 'decision' => 'approved', 'note' => 'Source no longer authorized', 'acknowledge' => true])->assertNotFound();
        $this->getJson('/ops/delegations/'.$id)->assertJsonPath('delegation.effective_status', 'invalid');
    }

    public function test_end_of_term_is_exclusive_at_the_exact_recorded_time(): void
    {
        $qa = $this->inspection();
        $id = $this->delegation();
        $end = DB::table('temporary_delegations')->where('id', $id)->value('ends_at');
        $this->travelTo(CarbonImmutable::parse($end, 'UTC'));
        $this->getJson('/ops/quality/'.$qa)->assertNotFound();
        $this->getJson('/ops/delegations/'.$id)->assertJsonPath('delegation.effective_status', 'expired');
    }
}
