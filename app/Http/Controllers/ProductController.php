<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'itemCategory']);
        if ($request->search) $query->search($request->search);
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->item_category_id) $query->where('item_category_id', $request->item_category_id);
        if ($request->status !== null) $query->where('is_active', $request->status);
        $products = $query->latest()->paginate(20)->withQueryString();
        $categories = Category::all();
        $itemCategories = ItemCategory::active()->orderBy('name')->get();

        return Inertia::render('Products/Index', [
            'products' => [
                'data'  => collect($products->items())->map(fn (Product $p) => [
                    'id'             => $p->id,
                    'name'           => $p->name,
                    'sku'            => $p->sku,
                    'category_name'  => $p->category?->name,
                    'item_category_name' => $p->itemCategory?->name,
                    'price'          => $p->price,
                    'stock'          => $p->stock,
                    'unit'           => $p->unit,
                    'track_stock'    => $p->track_stock,
                    'is_low_stock'   => $p->isLowStock(),
                    'erp_synced'     => (bool) $p->erp_item_code,
                    'is_active'      => $p->is_active,
                    'edit_url'       => route('products.edit', $p),
                ]),
                'links' => $products->linkCollection()->toArray(),
                'from'  => $products->firstItem(),
                'to'    => $products->lastItem(),
                'total' => $products->total(),
            ],
            'categories'     => $categories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'itemCategories' => $itemCategories->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'filters'        => $request->only(['search', 'category_id', 'item_category_id']),
            'indexUrl'       => route('products.index'),
            'createUrl'      => route('products.create'),
        ]);
    }

    private function formProps(?Product $product = null): array
    {
        return [
            'product'        => $product ? [
                'id' => $product->id, 'name' => $product->name, 'sku' => $product->sku,
                'barcode' => $product->barcode, 'category_id' => $product->category_id,
                'item_category_id' => $product->item_category_id, 'price' => $product->price,
                'cost_price' => $product->cost_price, 'stock' => $product->stock,
                'min_stock' => $product->min_stock, 'unit' => $product->unit,
                'tax_rate' => $product->tax_rate, 'description' => $product->description,
                'is_active' => $product->is_active, 'track_stock' => $product->track_stock,
            ] : null,
            'categories'     => Category::where('is_active', true)->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'itemCategories' => ItemCategory::active()->orderBy('name')->get()->map(fn ($c) => ['id' => $c->id, 'name' => $c->name]),
            'indexUrl'       => route('products.index'),
            'submitUrl'      => $product ? route('products.update', $product) : route('products.store'),
        ];
    }

    public function create()
    {
        return Inertia::render('Products/Form', $this->formProps());
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products',
            'barcode' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'item_category_id' => 'nullable|exists:item_categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
        ]);
        Product::create($data);
        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function edit(Product $product)
    {
        return Inertia::render('Products/Form', $this->formProps($product));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'item_category_id' => 'nullable|exists:item_categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
        ]);
        $product->update($data);
        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['success' => true]);
    }
}
