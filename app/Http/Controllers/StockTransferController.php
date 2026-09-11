<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Setting;
use App\Models\StockTransfer;
use App\Models\StockTransferItem;
use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StockTransferController extends Controller
{
    public function __construct(private ErpNextService $erp) {}

    // ----------------------------------------------------------
    // LIST — all transfers
    // ----------------------------------------------------------
    public function index(Request $request)
    {
        $query = StockTransfer::with('user')
            ->when($request->type, fn ($q, $v) => $q->where('type', $v))
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->search, fn ($q, $v) => $q->where('transfer_no', 'like', "%{$v}%")
                ->orWhere('from_warehouse', 'like', "%{$v}%")
                ->orWhere('to_warehouse', 'like', "%{$v}%")
            )
            ->latest();

        $transfers = $query->paginate(20)->withQueryString();

        return Inertia::render('StockTransfer/Index', [
            'transfers' => [
                'data' => collect($transfers->items())->map(fn (StockTransfer $t) => [
                    'id' => $t->id,
                    'transfer_no' => $t->transfer_no,
                    'type' => $t->type,
                    'from_warehouse' => $t->from_warehouse,
                    'to_warehouse' => $t->to_warehouse,
                    'erp_stock_entry' => $t->erp_stock_entry,
                    'local_status' => $t->local_status,
                    'erp_sync_status' => $t->erp_sync_status,
                    'created_at' => local_dt($t->created_at),
                    'show_url' => route('stock-transfer.show', $t),
                ]),
                'links' => $transfers->linkCollection()->toArray(),
                'from' => $transfers->firstItem(),
                'to' => $transfers->lastItem(),
                'total' => $transfers->total(),
            ],
            'filters' => $request->only(['search', 'type', 'status']),
            'indexUrl' => route('stock-transfer.index'),
            'reportUrl' => route('stock-transfer.report'),
            'sendUrl' => route('stock-transfer.send.create'),
            'receiveUrl' => route('stock-transfer.receive.create'),
        ]);
    }

    // ----------------------------------------------------------
    // SEND — form
    // ----------------------------------------------------------
    public function createSend()
    {
        $warehouses = Warehouse::activeList();
        $productData = $this->mapProductsForJs();

        return Inertia::render('StockTransfer/Send', [
            'warehouses' => $warehouses->map(fn ($w) => [
                'name' => $w->name, 'warehouse_name' => $w->warehouse_name, 'is_group' => (bool) $w->is_group,
            ]),
            'products' => $productData,
            'indexUrl' => route('stock-transfer.index'),
            'storeUrl' => route('stock-transfer.send.store'),
            'warehousesIndexUrl' => route('warehouses.index'),
        ]);
    }

    // ----------------------------------------------------------
    // SEND — store
    // ----------------------------------------------------------
    public function storeSend(Request $request)
    {
        $request->validate([
            'from_warehouse' => 'required|string',
            'to_warehouse' => 'required|string|different:from_warehouse',
            'in_transit_warehouse' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'transfer_no' => StockTransfer::generateTransferNo('outgoing'),
                'type' => 'outgoing',
                'status' => 'draft',
                'local_status' => 'sent',
                'from_warehouse' => $request->from_warehouse,
                'to_warehouse' => $request->to_warehouse,
                'in_transit_warehouse' => $request->in_transit_warehouse,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
                'erp_sync_status' => 'pending',
            ]);

            foreach ($request->items as $row) {
                $product = Product::where('erp_item_code', $row['item_code'])
                    ->orWhere('sku', $row['item_code'])
                    ->first();

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $product?->id,
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'sku' => $product?->sku ?? $row['item_code'],
                    'quantity' => $row['quantity'],
                    'unit' => $row['unit'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            DB::commit();

            // Sync to ERP HPY immediately
            $transfer->load('items');
            $result = $this->erp->createOutgoingTransfer($transfer);

            if ($result['success']) {
                return redirect()->route('stock-transfer.show', $transfer)
                    ->with('success', "Transfer {$transfer->transfer_no} berhasil dikirim ke ERP HPY ({$result['docname']}).");
            }

            return redirect()->route('stock-transfer.show', $transfer)
                ->with('error', "Transfer disimpan, tapi sync ERP HPY gagal: {$result['error']}");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Gagal menyimpan: '.$e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // RECEIVE — form (show pending in-transit from ERP HPY)
    // ----------------------------------------------------------
    public function createReceive()
    {
        $warehouses = Warehouse::activeList();

        $pendingResult = $this->erp->getPendingInTransitEntries();
        $pendingEntries = $pendingResult['success'] ? $pendingResult['data'] : [];
        $productData = $this->mapProductsForJs();

        return Inertia::render('StockTransfer/Receive', [
            'warehouses' => $warehouses->map(fn ($w) => [
                'name' => $w->name, 'warehouse_name' => $w->warehouse_name, 'is_group' => (bool) $w->is_group,
            ]),
            'pendingEntries' => $pendingEntries,
            'products' => $productData,
            'indexUrl' => route('stock-transfer.index'),
            'storeUrl' => route('stock-transfer.receive.store'),
            'loadItemsUrl' => route('stock-transfer.load-items'),
            'warehousesIndexUrl' => route('warehouses.index'),
        ]);
    }

    // ----------------------------------------------------------
    // RECEIVE — load items from a specific ERP HPY Stock Entry
    // ----------------------------------------------------------
    public function loadEntryItems(Request $request)
    {
        $request->validate(['entry_name' => 'required|string']);

        $result = $this->erp->getStockEntryDetail($request->entry_name);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']]);
        }

        $entry = $result['data'];
        $items = collect($entry['items'] ?? [])->map(function ($item) {
            $product = Product::where('erp_item_code', $item['item_code'])
                ->orWhere('sku', $item['item_code'])
                ->first();

            return [
                'item_code' => $item['item_code'],
                'item_name' => $item['item_name'],
                'quantity' => $item['qty'],
                'unit' => $item['uom'] ?? 'Nos',
                'product_id' => $product?->id,
                'local_name' => $product?->name,
            ];
        });

        return response()->json([
            'success' => true,
            'items' => $items,
            'from_warehouse' => $entry['from_warehouse'] ?? '',
            'to_warehouse' => $entry['to_warehouse'] ?? '',
        ]);
    }

    // ----------------------------------------------------------
    // RECEIVE — store
    // ----------------------------------------------------------
    public function storeReceive(Request $request)
    {
        $request->validate([
            'from_warehouse' => 'required|string',
            'to_warehouse' => 'required|string',
            'erp_source_entry' => 'nullable|string',
            'notes' => 'nullable|string|max:500',
            'items' => 'required|array|min:1',
            'items.*.item_code' => 'required|string',
            'items.*.item_name' => 'required|string',
            'items.*.quantity' => 'required|numeric|min:0.001',
            'items.*.actual_quantity' => 'required|numeric|min:0.001',
            'items.*.unit' => 'required|string',
        ]);

        DB::beginTransaction();
        try {
            $transfer = StockTransfer::create([
                'transfer_no' => StockTransfer::generateTransferNo('incoming'),
                'type' => 'incoming',
                'status' => 'draft',
                'local_status' => 'received',
                'from_warehouse' => $request->from_warehouse,
                'to_warehouse' => $request->to_warehouse,
                'notes' => $request->notes,
                'user_id' => auth()->id(),
                'erp_source_entry' => $request->erp_source_entry,
                'erp_sync_status' => 'pending',
            ]);

            foreach ($request->items as $row) {
                $product = Product::where('erp_item_code', $row['item_code'])
                    ->orWhere('sku', $row['item_code'])
                    ->first();

                StockTransferItem::create([
                    'stock_transfer_id' => $transfer->id,
                    'product_id' => $product?->id,
                    'item_code' => $row['item_code'],
                    'item_name' => $row['item_name'],
                    'sku' => $product?->sku ?? $row['item_code'],
                    'quantity' => $row['quantity'],
                    'actual_quantity' => $row['actual_quantity'],
                    'unit' => $row['unit'],
                    'notes' => $row['notes'] ?? null,
                ]);
            }

            DB::commit();

            $transfer->load('items.product');
            $result = $this->erp->createIncomingReceipt($transfer);

            if ($result['success']) {
                return redirect()->route('stock-transfer.show', $transfer)
                    ->with('success', "Penerimaan {$transfer->transfer_no} berhasil disubmit ke ERP HPY ({$result['docname']}).");
            }

            return redirect()->route('stock-transfer.show', $transfer)
                ->with('error', "Penerimaan disimpan, tapi sync ERP HPY gagal: {$result['error']}");

        } catch (\Exception $e) {
            DB::rollBack();

            return back()->withInput()->with('error', 'Gagal menyimpan: '.$e->getMessage());
        }
    }

    // ----------------------------------------------------------
    // SHOW — detail
    // ----------------------------------------------------------
    public function show(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items.product', 'user']);

        return Inertia::render('StockTransfer/Show', [
            'transfer' => [
                'id' => $stockTransfer->id,
                'transfer_no' => $stockTransfer->transfer_no,
                'type' => $stockTransfer->type,
                'is_incoming' => $stockTransfer->isIncoming(),
                'status' => $stockTransfer->status,
                'local_status' => $stockTransfer->local_status,
                'erp_sync_status' => $stockTransfer->erp_sync_status,
                'erp_stock_entry' => $stockTransfer->erp_stock_entry,
                'erp_source_entry' => $stockTransfer->erp_source_entry,
                'erp_sync_error' => $stockTransfer->erp_sync_error,
                'from_warehouse' => $stockTransfer->from_warehouse,
                'to_warehouse' => $stockTransfer->to_warehouse,
                'notes' => $stockTransfer->notes,
                'user_name' => $stockTransfer->user->name,
                'created_at' => local_dt($stockTransfer->created_at, 'd M Y H:i'),
                'submitted_at' => $stockTransfer->submitted_at?->format('d/m/Y H:i'),
                'items' => $stockTransfer->items->map(fn ($item) => [
                    'item_code' => $item->item_code,
                    'item_name' => $item->item_name,
                    'quantity' => $item->quantity,
                    'actual_quantity' => $item->actual_quantity,
                    'unit' => $item->unit,
                    'product_name' => $item->product?->name,
                ]),
            ],
            'canRetry' => $stockTransfer->erp_sync_status === 'failed'
                || ($stockTransfer->erp_sync_status === 'pending' && $stockTransfer->status === 'draft'),
            'retryUrl' => route('stock-transfer.retry', $stockTransfer),
            'suratJalanUrl' => route('stock-transfer.surat-jalan', $stockTransfer),
            'indexUrl' => route('stock-transfer.index'),
        ]);
    }

    // ----------------------------------------------------------
    // SURAT JALAN — standalone print page
    // ----------------------------------------------------------
    public function suratJalan(StockTransfer $stockTransfer)
    {
        $stockTransfer->load(['items.product', 'user']);
        $storeName = Setting::get('store_name', 'HPY');

        return view('stock-transfer.surat-jalan', [
            'transfer' => $stockTransfer,
            'storeName' => $storeName,
        ]);
    }

    // ----------------------------------------------------------
    // RETRY SYNC to ERP HPY
    // ----------------------------------------------------------
    public function retry(StockTransfer $stockTransfer)
    {
        $stockTransfer->load('items.product');

        $result = $stockTransfer->isOutgoing()
            ? $this->erp->createOutgoingTransfer($stockTransfer)
            : $this->erp->createIncomingReceipt($stockTransfer);

        if (request()->wantsJson()) {
            return response()->json($result);
        }

        $msg = $result['success']
            ? "Sync berhasil: {$result['docname']}"
            : "Sync gagal: {$result['error']}";

        return back()->with($result['success'] ? 'success' : 'error', $msg);
    }

    // ----------------------------------------------------------
    // HELPER — map products to JS-safe array (avoid multi-line @json in Blade)
    // ----------------------------------------------------------
    private function mapProductsForJs(): array
    {
        return Product::where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'sku', 'erp_item_code', 'unit'])
            ->map(fn ($p) => [
                'id' => $p->id,
                'name' => $p->name,
                'sku' => $p->sku,
                'item_code' => $p->erp_item_code ?? $p->sku,
                'unit' => $p->unit ?? 'Nos',
            ])
            ->values()
            ->toArray();
    }
}
