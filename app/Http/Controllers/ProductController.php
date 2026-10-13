<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use App\Services\BarcodeService;
use App\Services\ProductBarcodeService;
use App\Services\ProductPricingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $products = Product::with(['category', 'supplier', 'primaryBarcode'])
            ->when($request->search, function ($query, $search) {
                $query->where('name', 'like', "%{$search}%")
                    ->orWhere('code', 'like', "%{$search}%")
                    ->orWhere('sku', 'like', "%{$search}%")
                    ->orWhereHas('barcodes', fn ($barcodes) => $barcodes->where('code', 'like', "%{$search}%"));
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('products.index', compact('products'));
    }

    public function image(string $path)
    {
        abort_if(str_starts_with($path, '/') || str_contains($path, '..'), 404);
        abort_unless(str_starts_with($path, 'products/'), 404);
        abort_unless(Storage::disk('public')->exists($path), 404);

        return response(Storage::disk('public')->get($path), 200)
            ->header('Content-Type', Storage::disk('public')->mimeType($path) ?? 'application/octet-stream');
    }

    public function barcodeLabel(Product $product)
    {
        $product->load('primaryBarcode');
        abort_unless($product->primaryBarcode?->code, 404, 'Este producto no tiene un código de barras para imprimir.');

        return view('products.barcode-label', compact('product'));
    }

    public function create()
    {
        return view('products.create', $this->formData());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $imagePath = $this->storeImage($request);

        DB::transaction(function () use ($data, $imagePath): void {
            $barcodeType = $data['barcode_type'];
            $barcode = $data['barcode'] ?? null;
            unset($data['barcode'], $data['barcode_type'], $data['image']);
            $data['image_path'] = $imagePath;

            $product = Product::create($data);

            if (! filled($product->sku)) {
                $product->update(['sku' => app(BarcodeService::class)->generateSku($product)]);
            }

            app(ProductBarcodeService::class)->syncPrimaryBarcode($product, $barcode, $barcodeType, auth()->id());
        });

        return redirect()->route('products.index')->with('success', 'Producto creado.');
    }

    public function edit(Product $product)
    {
        return view('products.edit', $this->formData() + compact('product'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $this->validated($request, $product);
        $imagePath = $this->storeImage($request, $product);

        DB::transaction(function () use ($data, $product, $imagePath): void {
            $barcodeType = $data['barcode_type'];
            $barcode = $data['barcode'] ?? null;
            unset($data['barcode'], $data['barcode_type'], $data['image']);

            if ($imagePath) {
                $data['image_path'] = $imagePath;
            }

            $product->update($data);
            app(ProductBarcodeService::class)->syncPrimaryBarcode($product, $barcode, $barcodeType, auth()->id());
        });

        return redirect()->route('products.index')->with('success', 'Producto actualizado.');
    }

    public function destroy(Product $product)
    {
        if ($product->saleItems()->exists()) {
            return back()->withErrors('No se puede eliminar un producto vendido.');
        }

        $product->delete();

        return back()->with('success', 'Producto eliminado.');
    }

    private function formData(): array
    {
        return [
            'categories' => Category::where('active', true)->orderBy('name')->get(),
            'suppliers' => Supplier::where('active', true)->orderBy('name')->get(),
        ];
    }

    private function validated(Request $request, ?Product $product = null): array
    {
        $data = $request->validate([
            'category_id' => ['required', 'exists:categories,id'],
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'code' => ['required', 'string', 'max:80'],
            'sku' => ['nullable', 'string', 'max:80', 'unique:products,sku,'.($product?->id ?? 'NULL')],
            'barcode' => [
                'nullable',
                'string',
                'max:80',
                Rule::unique('product_barcodes', 'code')->ignore($product?->primaryBarcode?->id),
            ],
            'barcode_type' => ['required', Rule::in(BarcodeService::TYPES)],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
            'purchase_price' => ['required', 'numeric', 'min:0'],
            'profit_margin' => ['required', 'numeric', 'min:0', 'max:999.99'],
            'stock' => ['required', 'integer', 'min:0'],
            'min_stock' => ['required', 'integer', 'min:0'],
            'active' => ['nullable', 'boolean'],
        ]) + ['active' => false];

        $pricing = app(ProductPricingService::class)->calculate((float) $data['purchase_price'], (float) $data['profit_margin']);
        $data['purchase_tax_rate'] = $pricing['purchase_tax_rate'];
        $data['tax_rate'] = $pricing['tax_rate'];
        $data['sale_price'] = $pricing['sale_price'];

        return $data;
    }

    private function storeImage(Request $request, ?Product $product = null): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        if ($product?->image_path) {
            Storage::disk('public')->delete($product->image_path);
        }

        return $request->file('image')->store('products', 'public');
    }
}
