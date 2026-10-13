<div class="form-grid">
    <label>Codigo<input name="code" value="{{ old('code', $product->code ?? '') }}" required></label>
    <label>SKU<input name="sku" value="{{ old('sku', $product->sku ?? '') }}" placeholder="Se genera automaticamente si lo dejas vacio"></label>
    <label>Nombre<input name="name" value="{{ old('name', $product->name ?? '') }}" required></label>
    <label>Categoria<select name="category_id" required><option value="">Seleccione</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $product->category_id ?? '') == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
    <label>Proveedor<select name="supplier_id"><option value="">Sin proveedor</option>@foreach($suppliers as $supplier)<option value="{{ $supplier->id }}" @selected(old('supplier_id', $product->supplier_id ?? '') == $supplier->id)>{{ $supplier->name }}</option>@endforeach</select></label>
    <label class="span-2">Imagen del producto<input type="file" name="image" accept="image/jpeg,image/png,image/webp"></label>
    @if(! empty($product?->image_url))
        <div class="span-2"><img src="{{ $product->image_url }}" alt="{{ $product->name }}" style="width:96px;height:96px;object-fit:cover;border:1px solid var(--line);border-radius:8px;"></div>
    @endif
    <label>Codigo de barras<input name="barcode" value="{{ old('barcode', $product->primaryBarcode->code ?? '') }}" placeholder="Escanea o escribe el codigo"></label>
    <label>Tipo de codigo<select name="barcode_type" required>
        @foreach(['CODE128' => 'Code 128', 'EAN13' => 'EAN-13', 'EAN8' => 'EAN-8', 'UPCA' => 'UPC-A'] as $value => $label)
            <option value="{{ $value }}" @selected(old('barcode_type', $product->primaryBarcode->type ?? 'CODE128') === $value)>{{ $label }}</option>
        @endforeach
    </select></label>
    <label>Costo neto (sin IVA)<input id="purchasePrice" type="number" step="0.01" min="0" name="purchase_price" value="{{ old('purchase_price', $product->purchase_price ?? 0) }}" required></label>
    <label>IVA de compra<input value="19%" readonly aria-label="IVA de compra fijo del 19 por ciento"></label>
    <label>Margen comercial (%)<input id="profitMargin" type="number" step="0.01" min="0" max="999.99" name="profit_margin" value="{{ old('profit_margin', $product->profit_margin ?? '') }}" placeholder="Ej: 37.5" required></label>
    <div class="panel" style="padding:12px;"><strong>Precio calculado</strong><div class="muted" id="priceBreakdown">Indica costo y margen.</div><div class="metric" id="customerPrice">$0</div></div>
    <input type="hidden" name="purchase_tax_rate" value="19"><input type="hidden" name="tax_rate" value="19">
    <label>Stock<input type="number" min="0" name="stock" value="{{ old('stock', $product->stock ?? 0) }}" required></label>
    <label>Stock minimo<input type="number" min="0" name="min_stock" value="{{ old('min_stock', $product->min_stock ?? 1) }}" required></label>
    <label class="span-2">Descripcion<textarea name="description">{{ old('description', $product->description ?? '') }}</textarea></label>
    <label style="display:flex;gap:8px;align-items:center;font-weight:400;"><input type="checkbox" name="active" value="1" style="width:auto;" @checked(old('active', $product->active ?? true))> Activo</label>
</div><div class="actions" style="margin-top:14px;"><button class="btn">Guardar</button><a class="btn light" href="{{ route('products.index') }}">Cancelar</a></div>
<script>
(() => {
    const cost = document.getElementById('purchasePrice');
    const margin = document.getElementById('profitMargin');
    const breakdown = document.getElementById('priceBreakdown');
    const customerPrice = document.getElementById('customerPrice');
    const money = value => '$' + Math.round(value).toLocaleString('es-CO');
    const update = () => {
        const netCost = Number(cost.value || 0);
        const selectedMargin = Number(margin.value || 0);
        if (netCost < 0 || margin.value === '' || selectedMargin < 0) { breakdown.textContent = 'Indica costo y margen.'; customerPrice.textContent = '$0'; return; }
        const purchaseTax = netCost * .19;
        const costWithPurchaseTax = netCost + purchaseTax;
        const saleBeforeTax = costWithPurchaseTax * (1 + selectedMargin / 100);
        const saleTax = saleBeforeTax * .19;
        breakdown.textContent = `IVA compra: ${money(purchaseTax)} · Costo real: ${money(costWithPurchaseTax)} · Venta sin IVA: ${money(saleBeforeTax)} · IVA cliente (19%): ${money(saleTax)}`;
        customerPrice.textContent = `Total cliente: ${money(saleBeforeTax + saleTax)}`;
    };
    cost.addEventListener('input', update); margin.addEventListener('input', update); update();
})();
</script>
