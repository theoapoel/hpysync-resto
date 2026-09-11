<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Slice;
use App\Models\SliceLine;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class SliceController extends Controller
{
    public function index(Request $request)
    {
        $query = Slice::with('creator')->withCount(['issues', 'receipts'])->orderByDesc('id');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->search) {
            $query->where('slice_no', 'like', '%'.$request->search.'%');
        }

        $slices = $query->paginate(20)->withQueryString();

        return Inertia::render('Slices/Index', [
            'slices' => [
                'data' => collect($slices->items())->map(fn (Slice $s) => [
                    'id' => $s->id,
                    'slice_no' => $s->slice_no,
                    'creator_name' => $s->creator->name ?? '—',
                    'created_at' => $s->created_at->isoFormat('D MMM Y HH:mm'),
                    'issues_count' => $s->issues_count,
                    'receipts_count' => $s->receipts_count,
                    'status' => $s->status,
                    'status_label' => $s->status_label,
                    'erp_stock_entry' => $s->erp_stock_entry,
                    'erp_sync_status' => $s->erp_sync_status,
                    'show_url' => route('slices.show', $s),
                ]),
                'links' => $slices->linkCollection()->toArray(),
            ],
            'filters' => $request->only(['status', 'date_from', 'date_to', 'search']),
            'indexUrl' => route('slices.index'),
            'createUrl' => route('slices.create'),
        ]);
    }

    public function create()
    {
        $products = Product::where('is_active', true)->inItemGroups('slice_item_groups')->orderBy('name')
            ->get(['id', 'name', 'sku', 'erp_item_code', 'unit']);

        $warehouses = Warehouse::activeList();
        $defaultWarehouse = Warehouse::getDefault()?->name;

        return Inertia::render('Slices/Create', [
            'products' => $products,
            'warehouses' => $warehouses->map(fn ($w) => ['name' => $w->name, 'label' => $w->display_name]),
            'defaultWarehouse' => $defaultWarehouse,
            'indexUrl' => route('slices.index'),
            'storeUrl' => route('slices.store'),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'notes' => 'nullable|string|max:1000',
            'issues' => 'required|array|min:1',
            'issues.*.product_id' => 'required|integer|exists:products,id',
            'issues.*.qty' => 'required|numeric|min:0.01',
            'issues.*.warehouse' => 'required|string|max:140',
            'issues.*.notes' => 'nullable|string|max:255',
            'receipts' => 'required|array|min:1',
            'receipts.*.product_id' => 'required|integer|exists:products,id',
            'receipts.*.qty' => 'required|numeric|min:0.01',
            'receipts.*.notes' => 'nullable|string|max:255',
        ], [
            'issues.*.warehouse.required' => 'Gudang asal wajib dipilih untuk setiap baris item yang dibuang.',
        ]);

        $slice = DB::transaction(function () use ($request) {
            $slice = Slice::create([
                'slice_no' => Slice::generateSliceNo(),
                'created_by' => auth()->id(),
                'status' => 'draft',
                'notes' => $request->notes,
            ]);

            $build = function (array $rows, string $type, bool $withWarehouse) use ($slice) {
                foreach (array_values($rows) as $i => $row) {
                    $product = Product::find($row['product_id']);
                    SliceLine::create([
                        'slice_id' => $slice->id,
                        'line_type' => $type,
                        'sort_order' => $i,
                        'product_id' => $product->id,
                        'item_name' => $product->name,
                        'item_code' => $product->erp_item_code ?: $product->sku,
                        'qty' => $row['qty'],
                        'uom' => $product->unit ?: 'Nos',
                        'warehouse' => $withWarehouse ? ($row['warehouse'] ?? null ?: null) : null,
                        'notes' => $row['notes'] ?? null,
                    ]);
                }
            };

            $build($request->issues, 'issue', true);
            $build($request->receipts, 'receipt', false);

            return $slice;
        });

        return redirect()->route('slices.show', $slice)
            ->with('success', 'Repack '.$slice->slice_no.' berhasil dibuat.');
    }

    public function show(Slice $slice)
    {
        $slice->load('creator', 'issues', 'receipts');

        $line = fn ($l) => [
            'item_name' => $l->item_name, 'item_code' => $l->item_code,
            'qty' => $l->qty, 'uom' => $l->uom,
            'warehouse' => $l->warehouse, 'notes' => $l->notes,
        ];

        return Inertia::render('Slices/Show', [
            'slice' => [
                'id' => $slice->id,
                'slice_no' => $slice->slice_no,
                'creator_name' => $slice->creator->name ?? '—',
                'created_at' => $slice->created_at->isoFormat('D MMM Y, HH:mm'),
                'status' => $slice->status,
                'status_label' => $slice->status_label,
                'notes' => $slice->notes,
                'erp_stock_entry' => $slice->erp_stock_entry,
                'erp_sync_status' => $slice->erp_sync_status,
                'erp_sync_error' => $slice->erp_sync_error,
                'issues' => $slice->issues->map($line),
                'receipts' => $slice->receipts->map($line),
            ],
            'indexUrl' => route('slices.index'),
            'submitUrl' => route('slices.submit', $slice),
            'cancelUrl' => route('slices.cancel', $slice),
            'syncUrl' => route('slices.sync-erp', $slice),
        ]);
    }

    public function submit(Slice $slice)
    {
        if ($slice->status !== 'draft') {
            return back()->with('error', 'Hanya draft yang bisa disubmit.');
        }

        $slice->load('issues', 'receipts');

        $erp = new ErpNextService;
        if (! $erp->isConfigured()) {
            return back()->with('error', 'ERP HPY belum dikonfigurasi. Tidak dapat memproses konversi.');
        }

        $result = $erp->createRepackEntry($slice);

        if (! $result['success']) {
            return back()->with('error', 'Gagal memproses ke ERP: '.($result['error'] ?? 'Unknown error'));
        }

        $slice->update([
            'status' => 'submitted',
            'submitted_at' => now(),
        ]);

        return back()->with('success', 'Slice diproses. Stock Entry ERP: '.($result['docname'] ?? 'synced').'.');
    }

    public function cancel(Slice $slice)
    {
        if ($slice->status === 'cancelled') {
            return back()->with('error', 'Sudah dibatalkan.');
        }

        // Draft: belum masuk ERP, cukup tandai dibatalkan.
        if ($slice->status !== 'submitted') {
            $slice->update(['status' => 'cancelled']);

            return back()->with('success', 'Slice dibatalkan.');
        }

        // Submitted: batalkan dulu Stock Entry (Repack) di ERP supaya stok balik.
        if ($slice->erp_stock_entry) {
            $erp = new ErpNextService;
            if (! $erp->isConfigured()) {
                return back()->with('error', 'ERP HPY belum dikonfigurasi. Tidak dapat membatalkan Stock Entry.');
            }

            $result = $erp->cancelRepackEntry($slice);
            if (! $result['success']) {
                return back()->with('error', 'Gagal membatalkan Stock Entry di ERP: '.($result['error'] ?? 'Unknown error'));
            }
        }

        $slice->update([
            'status' => 'cancelled',
            'erp_sync_status' => 'pending',
            'erp_sync_error' => null,
        ]);

        return back()->with('success', 'Slice dibatalkan dan Stock Entry ERP dibatalkan.');
    }

    public function syncErp(Slice $slice)
    {
        if ($slice->erp_sync_status === 'synced') {
            return response()->json(['success' => false, 'error' => 'Sudah pernah disync.']);
        }

        $slice->load('issues', 'receipts');
        $erp = new ErpNextService;
        $result = $erp->createRepackEntry($slice);

        if ($result['success'] && $slice->status === 'draft') {
            $slice->update(['status' => 'submitted', 'submitted_at' => now()]);
        }

        return response()->json($result);
    }
}
