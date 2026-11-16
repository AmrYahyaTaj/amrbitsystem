<?php

namespace App\Http\Controllers;

use App\Enums\CompanyRole;
use App\Models\Driver;
use App\Models\HandoverFile;
use App\Models\HandoverMessage;
use App\Models\HandoverRequest;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Vehicle;
use App\Models\VehicleAssignment;
use App\Models\Warehouse;
use App\Models\WorkspaceNotification;
use App\Services\ArchiveWorkflow;
use App\Services\AuditRecorder;
use App\Services\HandoverWorkflow;
use App\Services\OperationOnce;
use App\Services\StockLedger;
use App\Services\WarehouseAccess;
use App\Support\CompanyContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class HandoverController extends Controller
{
    public function page(Request $request, OperationsController $operations)
    {
        return view('app', ['section' => 'handovers', 'sections' => $operations->sections(), 'handoverWorkspace' => true]);
    }

    private function row(HandoverRequest $h, HandoverWorkflow $workflow): array
    {
        $h->loadMissing('decider:id,name');
        $party = in_array(auth()->id(), [$h->sender_id, $h->created_by], true);

        return $h->toArray() + [
            'kind_label' => HandoverRequest::KINDS[$h->kind], 'can_respond' => $workflow->canRespond($h),
            'can_cancel' => $h->status === 'pending' && app(CompanyContext::class)->role !== CompanyRole::Viewer && ($party || ($h->kind !== 'cash' && app(CompanyContext::class)->role->managesOperations())),
            'document_url' => $h->status === 'accepted' ? (request()->is('api/*') ? '/api/v1/handovers/'.$h->id.'/document' : '/documents/handovers/'.$h->id) : null,
        ];
    }

    public function index(Request $request, HandoverWorkflow $workflow)
    {
        $d = $request->validate(['status' => 'nullable|in:pending,accepted,rejected,cancelled', 'kind' => 'nullable|in:vehicle_delivery,vehicle_return,stock_transfer,asset,cash', 'direction' => 'nullable|in:inbox,sent,all', 'q' => 'nullable|string|max:100', 'page' => 'nullable|integer|min:1']);
        $base = $workflow->visible();
        $query = (clone $base)->when($d['status'] ?? null, fn ($q, $status) => $q->where('status', $status))->when($d['kind'] ?? null, fn ($q, $kind) => $q->where('kind', $kind));
        if (($d['direction'] ?? null) === 'inbox') {
            $query->where(fn ($q) => $q->where('recipient_id', auth()->id())->when(in_array(app(CompanyContext::class)->role, [CompanyRole::Manager, CompanyRole::Accountant], true), fn ($cash) => $cash->orWhere(fn ($c) => $c->where('kind', 'cash')->whereNull('recipient_id'))));
        } elseif (($d['direction'] ?? null) === 'sent') {
            $query->where(fn ($q) => $q->where('sender_id', auth()->id())->orWhere('created_by', auth()->id()));
        }
        if (! empty($d['q'])) {
            $query->where(fn ($q) => $q->where('code', 'like', '%'.$d['q'].'%')->orWhere('sender_note', 'like', '%'.$d['q'].'%'));
        }
        $requests = $query->with(['sender:id,name', 'recipient:id,name'])->orderByDesc('id')->paginate(20);
        $requests->through(fn ($h) => $this->row($h, $workflow));
        $awaiting = (clone $base)->where('status', 'pending')->where('sender_id', '!=', auth()->id())->where('created_by', '!=', auth()->id())->where(function ($q) {
            $q->where(function ($recipient) {
                $recipient->where('recipient_id', auth()->id())->where(function ($kind) {
                    $role = app(CompanyContext::class)->role;
                    $kind->where('kind', 'asset');
                    if (in_array($role, [CompanyRole::Driver, CompanyRole::Representative], true)) {
                        $kind->orWhereIn('kind', ['vehicle_delivery', 'vehicle_return']);
                        $kind->orWhere(fn ($stock) => $stock->where('kind', 'stock_transfer')->whereIn('to_warehouse_id', Warehouse::where('type', 'vehicle')->select('id')));
                    }
                    if ($role->managesOperations() || in_array($role, [CompanyRole::WarehouseKeeper, CompanyRole::Production], true)) {
                        $kind->orWhere(fn ($stock) => $stock->where('kind', 'stock_transfer')->whereIn('to_warehouse_id', Warehouse::whereIn('type', ['warehouse', 'production'])->select('id')));
                    }
                });
            });
            if (in_array(app(CompanyContext::class)->role, [CompanyRole::Manager, CompanyRole::Accountant], true)) {
                $q->orWhere('kind', 'cash');
            }
        });
        if (app(CompanyContext::class)->role === CompanyRole::Viewer) {
            $awaiting->whereRaw('1 = 0');
        }

        return response()->json(['requests' => $requests, 'kinds' => HandoverRequest::KINDS, 'user_id' => auth()->id(), 'role' => app(CompanyContext::class)->role->value, 'can_create' => ! in_array(app(CompanyContext::class)->role, [CompanyRole::Viewer, CompanyRole::Accountant, CompanyRole::QualityInspector, CompanyRole::QualityManager], true), 'summary' => [
            'pending' => (clone $base)->where('status', 'pending')->count(),
            'awaiting_me' => $awaiting->count(),
            'accepted' => (clone $base)->where('status', 'accepted')->count(),
            'archived' => HandoverFile::whereIn('handover_id', (clone $base)->select('handover_requests.id'))->distinct()->count('handover_id'),
        ]]);
    }

    public function options(HandoverWorkflow $workflow)
    {
        $manager = app(CompanyContext::class)->role->managesOperations();
        abort_unless($manager || in_array(app(CompanyContext::class)->role, [CompanyRole::WarehouseKeeper, CompanyRole::Production, CompanyRole::Representative, CompanyRole::Driver], true), 403);
        $warehouses = Warehouse::where('is_active', true)->orderBy('name')->get();

        return response()->json(['users' => $workflow->recipients(), 'vehicles' => $manager ? Vehicle::orderBy('name')->get() : [], 'drivers' => $manager ? Driver::where('status', 'active')->get() : [], 'assignments' => $manager ? VehicleAssignment::with('driver')->whereNull('returned_at')->get() : [], 'warehouses' => $warehouses, 'source_warehouse_ids' => $warehouses->filter(fn ($w) => $w->type === 'vehicle' ? $workflow->stockOwner($w) === auth()->id() : app(WarehouseAccess::class)->managed($w))->pluck('id')->values(), 'products' => Product::where('is_active', true)->get(['id', 'name', 'sku', 'unit']), 'vehicle_types' => Vehicle::TYPES]);
    }

    public function stockAvailability(Request $request, HandoverWorkflow $workflow)
    {
        $d = $request->validate(['warehouse_id' => 'required|integer']);
        $warehouse = Warehouse::where('is_active', true)->findOrFail($d['warehouse_id']);
        $allowed = $warehouse->type === 'vehicle'
            ? in_array(app(CompanyContext::class)->role, [CompanyRole::Representative, CompanyRole::Driver], true) && $workflow->stockOwner($warehouse) === auth()->id()
            : app(WarehouseAccess::class)->managed($warehouse);
        abort_unless($allowed, 403, 'رصيد المصدر متاح لصاحب عهدته والإدارة فقط.');
        $products = Product::where('is_active', true)->whereIn('id', StockBalance::where('warehouse_id', $warehouse->id)->where('quantity', '>', 0)->select('product_id'))->orderBy('name')->get(['id', 'name', 'sku', 'unit']);
        $stock = app(StockLedger::class);

        return response()->json(['warehouse' => $warehouse->only(['id', 'name', 'type']), 'products' => $products->map(fn ($p) => $p->toArray() + $stock->availabilityDetails($warehouse->id, $p->id)), 'as_of' => now()->toIso8601String()]);
    }

    public function show(int $id, HandoverWorkflow $workflow)
    {
        request()->validate(['messages_page' => 'nullable|integer|min:1|max:100000']);
        $h = $workflow->find($id);

        return response()->json(['request' => $this->row($h, $workflow), 'messages' => HandoverMessage::where('handover_id', $id)->with('author:id,name')->orderByDesc('id')->paginate(30, ['*'], 'messages_page'), 'files' => HandoverFile::where('handover_id', $id)->orderByDesc('id')->get()->map(fn ($f) => $f->toArray() + ['download_url' => (request()->is('api/*') ? '/api/v1/handovers/files/' : '/ops/handovers/files/').$f->id])]);
    }

    public function create(Request $request, OperationOnce $once, HandoverWorkflow $workflow)
    {
        abort_unless(! in_array(app(CompanyContext::class)->role, [CompanyRole::Viewer, CompanyRole::Accountant, CompanyRole::QualityInspector, CompanyRole::QualityManager], true), 403);
        $d = $request->validate(['kind' => 'required|in:vehicle_delivery,vehicle_return,stock_transfer,asset', 'request_id' => 'required|uuid']);
        $allowed = match ($d['kind']) {
            'vehicle_delivery', 'vehicle_return' => app(CompanyContext::class)->role->managesOperations(),
            'asset' => app(CompanyContext::class)->role->managesOperations() || app(CompanyContext::class)->role === CompanyRole::WarehouseKeeper,
            'stock_transfer' => true,
        };
        abort_unless($allowed, 403);

        return response()->json($once->run('handover.create', $d['request_id'], $request->except('request_id', '_token'), function () use ($workflow, $request, $d) {
            $h = match ($d['kind']) {
                'vehicle_delivery' => $workflow->vehicle($request),
                'vehicle_return' => $workflow->vehicleReturn($request),
                'stock_transfer' => $workflow->stock($request),
                'asset' => $workflow->asset($request),
            };

            return ['id' => $h->id, 'message' => 'أرسل الطلب للمستلم؛ يبقى الاستلام معلقًا حتى إقراره من حسابه.'];
        }));
    }

    public function decision(Request $request, int $id, OperationOnce $once, HandoverWorkflow $workflow)
    {
        $h = $workflow->find($id);
        // Replays must still be made by the same eligible counterpart, not a new role.
        abort_unless($workflow->eligibleRecipient($h), 403);
        $d = $request->validate(['request_id' => 'required|uuid', 'decision' => 'required|in:accepted,rejected', 'note' => 'required|string|min:3|max:2000', 'acknowledge' => 'exclude_unless:decision,accepted|required|accepted']);

        return response()->json($once->run('handover.decision', $d['request_id'], ['id' => $id] + $request->except('request_id', '_token'), function () use ($workflow, $id, $d) {
            $result = $workflow->respond($id, $d['decision'], $d['note']);

            return ['id' => $result->id, 'message' => $result->status === 'accepted' ? 'تم إقرار الاستلام وإصدار المحضر القابل للطباعة.' : 'حفظ الرفض وملاحظاته، وأُبلغ الطرف الآخر.'];
        }));
    }

    public function cancel(Request $request, int $id, OperationOnce $once, HandoverWorkflow $workflow)
    {
        abort_if(app(CompanyContext::class)->role === CompanyRole::Viewer, 403);
        $h = $workflow->find($id);
        abort_unless(in_array(auth()->id(), [$h->sender_id, $h->created_by], true) || ($h->kind !== 'cash' && app(CompanyContext::class)->role->managesOperations()), 403);
        $d = $request->validate(['request_id' => 'required|uuid', 'note' => 'required|string|min:3|max:2000']);

        return response()->json($once->run('handover.cancel', $d['request_id'], ['id' => $id, 'note' => $d['note']], function () use ($workflow, $id, $d) {
            $workflow->cancel($id, $d['note']);

            return ['id' => $id, 'message' => 'أُلغي الطلب وحُررت حجوزاته مع حفظ تاريخه.'];
        }));
    }

    public function message(Request $request, int $id, OperationOnce $once, HandoverWorkflow $workflow)
    {
        $h = $workflow->find($id);
        abort_if(app(CompanyContext::class)->role === CompanyRole::Viewer, 403);
        $d = $request->validate(['request_id' => 'required|uuid', 'body' => 'required|string|min:1|max:2000']);

        return response()->json($once->run('handover.message', $d['request_id'], ['id' => $id, 'body' => $d['body']], function () use ($h, $workflow, $d) {
            $message = HandoverMessage::create(['handover_id' => $h->id, 'user_id' => auth()->id(), 'body' => $d['body'], 'created_at' => now()]);
            $workflow->notify($h, $workflow->participants($h), 'message', 'رسالة جديدة في طلب '.$h->code, Str::limit($d['body'], 150));

            return ['id' => $message->id, 'message' => 'حُفظت الرسالة وأُبلغ الطرف الآخر.'];
        }));
    }

    public function upload(Request $request, int $id, OperationOnce $once, HandoverWorkflow $workflow)
    {
        $h = $workflow->find($id);
        abort_if(app(CompanyContext::class)->role === CompanyRole::Viewer, 403);
        abort_unless($h->status === 'accepted', 422, 'أرشفة النسخة الموقعة متاحة بعد إقرار الاستلام.');
        $d = $request->validate(['request_id' => 'required|uuid', 'document' => 'required|file|mimes:pdf,jpg,jpeg,png|max:10240']);
        $file = $request->file('document');
        $hash = hash_file('sha256', $file->getRealPath());
        $name = Str::limit(basename(str_replace('\\', '/', $file->getClientOriginalName())), 180, '');
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name) ?: 'signed-document';
        $archiveName = (string) Str::uuid();
        $createdPath = null;
        try {
            $result = $once->run('handover.archive', $d['request_id'], ['id' => $id, 'sha256' => $hash, 'name' => $name], function () use ($h, $file, $hash, $name, $archiveName, &$createdPath, $workflow) {
                $extension = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'][$file->getMimeType()] ?? null;
                abort_unless($extension, 422, 'يسمح بأرشفة PDF أو صورة JPEG/PNG فقط.');
                $createdPath = $file->storeAs('handovers/'.$h->company_id.'/'.$h->id, $archiveName.'.'.$extension, 'local');
                abort_unless($createdPath, 500, 'تعذر حفظ الملف.');
                $archive = HandoverFile::create(['handover_id' => $h->id, 'user_id' => auth()->id(), 'original_name' => $name, 'path' => $createdPath, 'mime' => $file->getMimeType(), 'size' => $file->getSize(), 'sha256' => $hash, 'created_at' => now()]);
                app(ArchiveWorkflow::class)->registerHandover($archive);
                app(AuditRecorder::class)->record('handover.archived', $h, ['file_id' => $archive->id, 'sha256' => $hash]);
                $workflow->notify($h, $workflow->participants($h), 'archived', 'أُرشفت نسخة موقعة لمحضر الاستلام', $h->code.' • '.$name);

                return ['id' => $archive->id, 'message' => 'حفظت النسخة المرفوعة في الأرشيف الخاص دون استبدال الملفات السابقة.'];
            });
        } catch (\Throwable $e) {
            if ($createdPath) {
                Storage::disk('local')->delete($createdPath);
            }
            throw $e;
        }

        return response()->json($result);
    }

    public function download(int $id, HandoverWorkflow $workflow)
    {
        $file = HandoverFile::findOrFail($id);
        $workflow->find($file->handover_id);
        abort_unless(Storage::disk('local')->exists($file->path), 404);

        return Storage::disk('local')->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function document(int $id, HandoverWorkflow $workflow)
    {
        $h = $workflow->find($id);
        abort_unless($h->status === 'accepted' && $h->receipt && $h->receipt_hash, 422, 'لم يصدر إقرار استلام لهذا الطلب بعد.');
        $hash = hash('sha256', json_encode($h->receipt, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        abort_unless(hash_equals($h->receipt_hash, $hash), 409, 'بيانات المحضر لا تطابق بصمته المحفوظة.');

        return view('documents.handover', ['handover' => $h, 'receipt' => $h->receipt, 'kindLabel' => HandoverRequest::KINDS[$h->kind]]);
    }

    public function notifications(Request $request)
    {
        $request->validate(['page' => 'nullable|integer|min:1']);
        $query = WorkspaceNotification::where('user_id', auth()->id());

        return response()->json(['unread' => (clone $query)->whereNull('read_at')->count(), 'notifications' => $query->orderByDesc('id')->paginate(20)]);
    }

    public function readNotification(Request $request)
    {
        $d = $request->validate(['id' => 'nullable|integer', 'all' => 'nullable|boolean']);
        abort_unless($request->boolean('all') || isset($d['id']), 422);
        WorkspaceNotification::where('user_id', auth()->id())->whereNull('read_at')->when(! $request->boolean('all'), fn ($q) => $q->whereKey($d['id']))->update(['read_at' => now()]);

        return response()->json(['ok' => true]);
    }
}
