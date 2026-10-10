@extends('layouts.app')
@section('title', 'Nueva venta')
@section('content')
<h1>Nueva venta</h1>
<form class="panel" method="post" action="{{ route('sales.store') }}" id="saleForm">
    @csrf
    <div class="form-grid">
        <label>Tipo de factura<select name="invoice_type" id="invoiceType" required onchange="toggleInvoiceType()"><option value="normal" @selected(old('invoice_type') === 'normal')>Factura normal</option><option value="electronica" @selected(old('invoice_type') === 'electronica')>Factura electrónica</option></select></label>
        <label>Cliente<select name="customer_id" id="customerId"><option value="">Consumidor final</option>@foreach($customers as $customer)<option value="{{ $customer->id }}" @selected(old('customer_id') == $customer->id)>{{ $customer->name }} {{ $customer->document ? '- '.$customer->document : '' }}</option>@endforeach</select></label>
        <label>Metodo de pago<select name="payment_method" id="paymentMethod" required onchange="toggleCreditFields();calculate()"><option value="efectivo" @selected(old('payment_method', 'efectivo') === 'efectivo')>Efectivo</option><option value="transferencia" @selected(old('payment_method') === 'transferencia')>Transferencia</option><option value="tarjeta" @selected(old('payment_method') === 'tarjeta')>Tarjeta</option><option value="mixto" @selected(old('payment_method') === 'mixto')>Mixto</option><option value="credito" @selected(old('payment_method') === 'credito')>Credito</option></select></label>
        <label id="creditDueField" style="display:none;">Fecha vencimiento credito<input type="date" name="credit_due_date" id="creditDueDate" value="{{ old('credit_due_date', now()->addDays(30)->format('Y-m-d')) }}"></label>
    </div>
    <p class="muted" id="electronicNotice" style="display:none;margin-top:10px;">La factura electrónica se enviará a Factus. El cliente es obligatorio y debe tener sus datos fiscales completos.</p>
    <div class="panel" style="margin-top:18px;background:#111;color:#fff;border-color:#ef1d25;">
        <label>Escanear o escribir codigo
            <input id="barcodeInput" autocomplete="off" placeholder="Escanea el codigo y presiona Enter" style="margin-top:8px;">
        </label>
        <div id="scanMessage" style="margin-top:10px;font-weight:700;"></div>
    </div>
    <h2 style="margin-top:18px;">Productos y servicios</h2>
    <div id="items" class="grid"></div>
    <div class="actions" style="margin-top:10px;">
        <button class="btn light" type="button" onclick="addItem()">Agregar producto</button>
        <button class="btn secondary" type="button" onclick="addServiceItem()">Agregar servicio</button>
    </div>
    <div class="form-grid" style="margin-top:18px;">
        <label>Descuento<input type="number" step="0.01" min="0" name="discount" id="discount" value="{{ old('discount', 0) }}" oninput="calculate()"></label>
        <label>Valor pagado<input type="number" step="0.01" min="0" name="paid_amount" id="paid" value="{{ old('paid_amount', 0) }}" oninput="calculate()" required></label>
    </div>
    <div class="panel" id="mixedPayments" style="display:none;margin-top:14px;">
        <h2>Desglose del pago mixto</h2><p class="muted">Registra cada medio. La suma debe ser exactamente igual al total de la venta.</p>
        <div id="paymentLines" class="grid"></div><div class="actions" style="margin-top:10px;"><button type="button" class="btn light" onclick="addPaymentLine()">Agregar medio de pago</button></div>
    </div>
    <label id="creditPaymentMethod" style="display:none;margin-top:14px;">Medio del abono inicial<select name="credit_payment_method"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option><option value="otro">Otro</option></select></label>
    <div class="grid grid-4" style="margin-top:16px;">
        <div class="panel"><div class="muted">Subtotal</div><div class="metric" id="subtotal">$0</div></div>
        <div class="panel"><div class="muted">IVA</div><div class="metric" id="tax">$0</div></div>
        <div class="panel"><div class="muted">Total</div><div class="metric" id="total">$0</div></div>
        <div class="panel"><div class="muted" id="changeLabel">Cambio</div><div class="metric" id="change">$0</div></div>
    </div>
    <div class="actions" style="margin-top:16px;"><button class="btn">Registrar venta</button><a class="btn light" href="{{ route('sales.index') }}">Cancelar</a></div>
</form>
<style>
    .sale-item-grid {
        grid-template-columns:minmax(0, 1fr) minmax(120px, 150px) minmax(100px, 120px) 42px;
        align-items:end;
    }
    .sale-item-remove {
        width:42px;
        min-height:42px;
        padding:0;
    }
    .sale-item-remove svg {
        width:18px;
        height:18px;
        fill:none;
        stroke:currentColor;
        stroke-linecap:round;
        stroke-linejoin:round;
        stroke-width:2;
    }
    @media (max-width: 900px) {
        .sale-item-grid {
            grid-template-columns:minmax(0, 1fr) minmax(110px, 130px) minmax(90px, 110px) 42px;
        }
    }
    @media (max-width: 560px) {
        .sale-item-grid {
            grid-template-columns:minmax(0, 1fr) minmax(0, 1fr) 42px;
        }
        .sale-item-grid > label:first-of-type {
            grid-column:1 / -1;
        }
    }
</style>
<script>
let products = @json($productOptions);
let services = @json($serviceOptions);
const oldItems = @json(old('items', []));
let index = 0;
let paidAmountEdited = false;
function money(value) { return '$' + Math.round(value).toLocaleString('es-CO'); }
function productLabel(product) {
    const reference = product.barcode || product.sku || product.code;
    return `${reference} - ${product.name} ($${Math.round(product.price).toLocaleString('es-CO')}, IVA ${Number(product.tax_rate || 0).toLocaleString('es-CO')}%, stock ${product.stock})`;
}
function serviceLabel(service) {
    return `${service.code} - ${service.name} ($${Math.round(service.price).toLocaleString('es-CO')}, IVA ${Number(service.tax_rate || 0).toLocaleString('es-CO')}%)`;
}
function defaultPrice(items, itemId) {
    return items.find(item => String(item.id) === String(itemId))?.price || 0;
}
function syncRowPrice(select) {
    const priceInput = select.closest('.form-grid').querySelector('[data-unit-price]');
    priceInput.value = select.selectedOptions[0]?.dataset.price || 0;
    calculate();
}
function addItem(productId = '', unitPrice = null, quantity = 1) {
    const row = document.createElement('div');
    row.className = 'form-grid sale-item-grid';
    const price = unitPrice ?? defaultPrice(products, productId);
    row.innerHTML = `<input type="hidden" name="items[${index}][item_type]" value="product"><label>Producto<select name="items[${index}][product_id]" onchange="syncRowPrice(this)" required><option value="">Seleccione</option>${products.map(p => `<option value="${p.id}" data-price="${p.price}" data-tax-rate="${p.tax_rate || 0}" data-stock="${p.stock}" ${String(p.id) === String(productId) ? 'selected' : ''}>${productLabel(p)}</option>`).join('')}</select></label><label>Precio unitario<input data-unit-price type="number" step="0.01" min="0.01" name="items[${index}][unit_price]" value="${price}" oninput="calculate()" required></label><label>Cantidad<input data-quantity type="number" min="1" name="items[${index}][quantity]" value="${quantity}" oninput="calculate()" required></label><button class="btn danger sale-item-remove" type="button" onclick="confirmRemoveItem(this)" aria-label="Quitar producto" title="Quitar producto"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M9 7l1-2h4l1 2m-8 0 1 13h8l1-13"/></svg></button>`;
    document.getElementById('items').appendChild(row);
    index++;
    calculate();
    return row;
}
function addServiceItem(serviceId = '', unitPrice = null, quantity = 1) {
    const row = document.createElement('div');
    row.className = 'form-grid sale-item-grid';
    const price = unitPrice ?? defaultPrice(services, serviceId);
    row.innerHTML = `<input type="hidden" name="items[${index}][item_type]" value="service"><label>Servicio<select name="items[${index}][service_id]" onchange="syncRowPrice(this)" required><option value="">Seleccione</option>${services.map(s => `<option value="${s.id}" data-price="${s.price}" data-tax-rate="${s.tax_rate || 0}" ${String(s.id) === String(serviceId) ? 'selected' : ''}>${serviceLabel(s)}</option>`).join('')}</select></label><label>Precio unitario<input data-unit-price type="number" step="0.01" min="0.01" name="items[${index}][unit_price]" value="${price}" oninput="calculate()" required></label><label>Cantidad<input data-quantity type="number" min="1" name="items[${index}][quantity]" value="${quantity}" oninput="calculate()" required></label><button class="btn danger sale-item-remove" type="button" onclick="confirmRemoveItem(this)" aria-label="Quitar servicio" title="Quitar servicio"><svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 7h16M10 11v6m4-6v6M9 7l1-2h4l1 2m-8 0 1 13h8l1-13"/></svg></button>`;
    document.getElementById('items').appendChild(row);
    index++;
    calculate();
    return row;
}
function calculate() {
    let subtotal = 0;
    let tax = 0;
    document.querySelectorAll('#items .form-grid').forEach(row => {
        const select = row.querySelector('select');
        const quantity = parseInt(row.querySelector('[data-quantity]').value || '0', 10);
        const price = parseFloat(row.querySelector('[data-unit-price]').value || '0');
        const taxRate = parseFloat(select.selectedOptions[0]?.dataset.taxRate || '0');
        subtotal += price * quantity;
        tax += (price * quantity) * (taxRate / 100);
    });
    const discount = parseFloat(document.getElementById('discount').value || '0');
    const total = Math.max(subtotal - discount, 0) + tax;
    const paymentMethod = document.getElementById('paymentMethod').value;
    const paidInput = document.getElementById('paid');
    if (paymentMethod !== 'credito' && !paidAmountEdited) {
        paidInput.value = total.toFixed(2);
    }
    if (paymentMethod === 'credito' && !paidAmountEdited) {
        paidInput.value = '0';
    }
    const paid = parseFloat(paidInput.value || '0');
    document.getElementById('subtotal').textContent = money(subtotal);
    document.getElementById('tax').textContent = money(tax);
    document.getElementById('total').textContent = money(total);
    if (paymentMethod === 'credito') {
        document.getElementById('changeLabel').textContent = 'Saldo';
        document.getElementById('change').textContent = money(Math.max(total - paid, 0));
    } else {
        document.getElementById('changeLabel').textContent = 'Cambio';
        document.getElementById('change').textContent = money(Math.max(paid - total, 0));
    }
}
function toggleCreditFields() {
    const isCredit = document.getElementById('paymentMethod').value === 'credito';
    const isMixed = document.getElementById('paymentMethod').value === 'mixto';
    document.getElementById('creditDueField').style.display = isCredit ? 'grid' : 'none';
    document.getElementById('creditPaymentMethod').style.display = isCredit ? 'grid' : 'none';
    document.getElementById('mixedPayments').style.display = isMixed ? 'block' : 'none';
    document.getElementById('creditDueDate').required = isCredit;
    if (!isCredit) paidAmountEdited = false;
    calculate();
}
let paymentIndex = 0;
function addPaymentLine(method = 'efectivo', amount = '') {
    const row = document.createElement('div');
    row.className = 'form-grid';
    row.innerHTML = `<label>Medio<select name="payments[${paymentIndex}][payment_method]"><option value="efectivo" ${method === 'efectivo' ? 'selected' : ''}>Efectivo</option><option value="transferencia" ${method === 'transferencia' ? 'selected' : ''}>Transferencia</option><option value="tarjeta" ${method === 'tarjeta' ? 'selected' : ''}>Tarjeta</option><option value="otro" ${method === 'otro' ? 'selected' : ''}>Otro</option></select></label><label>Valor<input name="payments[${paymentIndex}][amount]" type="number" step="0.01" min="0.01" value="${amount}" required></label><button class="btn danger" type="button" onclick="this.parentElement.remove()">Quitar</button>`;
    document.getElementById('paymentLines').appendChild(row); paymentIndex++;
}
function toggleInvoiceType() {
    const electronic = document.getElementById('invoiceType').value === 'electronica';
    document.getElementById('customerId').required = electronic;
    document.getElementById('customerId').querySelector('option[value=""]').disabled = electronic;
    document.getElementById('electronicNotice').style.display = electronic ? 'block' : 'none';
}
function setScanMessage(message, type = 'ok') {
    const scanMessage = document.getElementById('scanMessage');
    scanMessage.textContent = message;
    scanMessage.style.color = type === 'error' ? '#fecaca' : '#bbf7d0';
}
function confirmRemoveItem(button) {
    const remove = () => {
        button.parentElement.remove();
        calculate();
    };

    if (! window.Swal) {
        if (window.confirm('¿Deseas quitar este ítem de la venta?')) remove();
        return;
    }

    Swal.fire({
        icon: 'warning',
        title: '¿Quitar este ítem?',
        text: 'El producto o servicio será eliminado de la venta actual.',
        showCancelButton: true,
        confirmButtonText: 'Sí, quitar',
        cancelButtonText: 'Cancelar',
        confirmButtonColor: '#b91c1c',
        cancelButtonColor: '#6b7280',
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) remove();
    });
}
function findProductRow(productId) {
    return Array.from(document.querySelectorAll('#items .form-grid')).find(row => {
        if (row.querySelector('input[type="hidden"]').value !== 'product') {
            return false;
        }

        return String(row.querySelector('select').value) === String(productId);
    });
}
function findEmptyProductRow() {
    return Array.from(document.querySelectorAll('#items .form-grid')).find(row => {
        return row.querySelector('input[type="hidden"]').value === 'product'
            && ! row.querySelector('select').value;
    });
}
function selectProductInRow(row, product) {
    const select = row.querySelector('select');
    let option = Array.from(select.options).find(item => String(item.value) === String(product.id));

    if (! option) {
        option = new Option(productLabel(product), product.id);
        option.dataset.price = product.price;
        option.dataset.taxRate = product.tax_rate || 0;
        option.dataset.stock = product.stock;
        select.add(option);
    }

    select.value = String(product.id);
    syncRowPrice(select);
}
function quantityInCart(productId) {
    return Array.from(document.querySelectorAll('#items .form-grid')).reduce((total, row) => {
        if (row.querySelector('input[type="hidden"]').value !== 'product') {
            return total;
        }

        if (String(row.querySelector('select').value) !== String(productId)) {
            return total;
        }

        return total + parseInt(row.querySelector('[data-quantity]').value || '0', 10);
    }, 0);
}
function upsertProductOption(product) {
    if (! products.some(item => String(item.id) === String(product.id))) {
        products.push(product);
    }
}
async function scanProduct(code) {
    if (! code) {
        return;
    }

    try {
        const response = await fetch(`{{ route('products.lookup') }}?code=${encodeURIComponent(code)}`, {
            headers: { 'Accept': 'application/json' }
        });
        const data = await response.json();

        if (! response.ok || ! data.success) {
            setScanMessage(data.message || 'Producto no encontrado.', 'error');
            return;
        }

        const product = data.product;
        upsertProductOption(product);

        if (quantityInCart(product.id) >= product.stock) {
            setScanMessage('No hay suficiente inventario.', 'error');
            return;
        }

        const existingRow = findProductRow(product.id);

        if (existingRow) {
            const quantityInput = existingRow.querySelector('[data-quantity]');
            quantityInput.value = parseInt(quantityInput.value || '0', 10) + 1;
        } else {
            const emptyRow = findEmptyProductRow();

            if (emptyRow) {
                selectProductInRow(emptyRow, product);
            } else {
                addItem(product.id);
            }
        }

        calculate();
        setScanMessage(`${product.name} agregado. Stock disponible: ${product.stock}.`);
    } catch (error) {
        setScanMessage('No se pudo buscar el producto.', 'error');
    }
}
document.getElementById('barcodeInput').addEventListener('keydown', function (event) {
    if (event.key !== 'Enter') {
        return;
    }

    event.preventDefault();
    const code = this.value.trim();
    this.value = '';
    scanProduct(code);
});
document.getElementById('barcodeInput').focus();
document.getElementById('paid').addEventListener('input', () => {
    paidAmountEdited = true;
});
document.getElementById('saleForm').addEventListener('submit', function (event) {
    const invalidPrice = Array.from(document.querySelectorAll('[data-unit-price]')).find(input => {
        return ! input.value || parseFloat(input.value) <= 0;
    });

    if (invalidPrice) {
        event.preventDefault();
        setScanMessage('Corrige el precio unitario: debe ser mayor que cero.', 'error');
        invalidPrice.focus();
    }
    if (document.getElementById('paymentMethod').value === 'mixto' && document.querySelectorAll('#paymentLines .form-grid').length < 2) {
        event.preventDefault(); setScanMessage('En el pago mixto registra al menos dos medios de pago.', 'error');
    }
});
if (oldItems.length) {
    oldItems.forEach(item => {
        if (item.item_type === 'service') {
            addServiceItem(item.service_id || '', item.unit_price ?? null, item.quantity || 1);
        } else {
            addItem(item.product_id || '', item.unit_price ?? null, item.quantity || 1);
        }
    });
} else {
    addItem();
}
toggleCreditFields();
toggleInvoiceType();
</script>
@endsection
