<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\Service;
use App\Services\CashReportService;
use App\Services\FactusService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SaleController extends Controller
{
    public function index()
    {
        return view('sales.index', [
            'sales' => Sale::with(['user', 'customer'])->latest()->paginate(12),
        ]);
    }

    public function create()
    {
        $products = Product::with('primaryBarcode')->where('active', true)->where('stock', '>', 0)->whereNotNull('sale_price')->orderBy('name')->get();
        $services = Service::where('active', true)->orderBy('name')->get();

        return view('sales.create', [
            'customers' => Customer::orderBy('name')->get(),
            'products' => $products,
            'productOptions' => $products->map(fn ($product) => [
                'id' => $product->id,
                'code' => $product->code,
                'sku' => $product->sku,
                'barcode' => $product->primaryBarcode?->code,
                'name' => $product->name,
                'price' => (float) $product->sale_price,
                'tax_rate' => (float) $product->tax_rate,
                'stock' => $product->stock,
                'image_url' => $product->image_url,
            ])->values(),
            'serviceOptions' => $services->map(fn ($service) => [
                'id' => $service->id,
                'code' => $service->code,
                'name' => $service->name,
                'price' => (float) $service->sale_price,
                'tax_rate' => (float) $service->tax_rate,
            ])->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'customer_id' => ['nullable', 'exists:customers,id'],
            'invoice_type' => ['required', 'in:normal,electronica'],
            'payment_method' => ['required', 'in:efectivo,transferencia,tarjeta,mixto,credito'],
            'credit_due_date' => ['nullable', 'date', 'required_if:payment_method,credito'],
            'discount' => ['nullable', 'numeric', 'min:0'],
            'paid_amount' => ['required', 'numeric', 'min:0'],
            'payments' => ['nullable', 'array'],
            'payments.*.payment_method' => ['required_with:payments', 'in:efectivo,transferencia,tarjeta,otro'],
            'payments.*.amount' => ['required_with:payments', 'numeric', 'min:0.01'],
            'credit_payment_method' => ['nullable', 'in:efectivo,transferencia,tarjeta,otro'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.item_type' => ['required', 'in:product,service'],
            'items.*.product_id' => ['nullable', 'exists:products,id'],
            'items.*.service_id' => ['nullable', 'exists:services,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0.01'],
        ], [
            'invoice_type.required' => 'Selecciona el tipo de factura.',
            'payment_method.required' => 'Selecciona el método de pago.',
            'credit_due_date.required_if' => 'Indica la fecha de vencimiento del crédito.',
            'paid_amount.required' => 'Indica el valor pagado.',
            'paid_amount.numeric' => 'El valor pagado debe ser un número válido.',
            'items.required' => 'Agrega al menos un producto o servicio a la venta.',
            'items.min' => 'Agrega al menos un producto o servicio a la venta.',
            'items.*.item_type.required' => 'Cada línea debe indicar si es producto o servicio.',
            'items.*.product_id.exists' => 'Uno de los productos seleccionados ya no está disponible.',
            'items.*.service_id.exists' => 'Uno de los servicios seleccionados ya no está disponible.',
            'items.*.quantity.required' => 'Indica la cantidad de cada producto o servicio.',
            'items.*.quantity.min' => 'La cantidad debe ser como mínimo 1.',
            'items.*.unit_price.required' => 'Indica el precio unitario de cada producto o servicio.',
            'items.*.unit_price.numeric' => 'El precio unitario debe ser un número válido.',
            'items.*.unit_price.min' => 'El precio unitario debe ser mayor que cero.',
        ]);

        $discount = (float) ($data['discount'] ?? 0);

        if ($data['invoice_type'] === 'electronica' && empty($data['customer_id'])) {
            return back()->withInput()->withErrors('Para facturar electrónicamente debes seleccionar un cliente.');
        }

        if ($data['invoice_type'] === 'electronica') {
            $customer = Customer::find($data['customer_id']);
            $missingFields = $this->missingElectronicCustomerFields($customer);

            if ($missingFields) {
                return back()->withInput()->withErrors('Completa los datos fiscales del cliente antes de facturar electrónicamente: '.implode(', ', $missingFields).'.');
            }
        }

        try {
            app(CashReportService::class)->assertDayIsOpen(now());
            $sale = DB::transaction(function () use ($data, $discount) {
                $subtotal = 0;
                $taxTotal = 0;
                $items = [];

                foreach ($data['items'] as $item) {
                    $quantity = (int) $item['quantity'];

                    if ($item['item_type'] === 'product') {
                        if (empty($item['product_id'])) {
                            throw new \RuntimeException('Selecciona un producto.');
                        }

                        $product = Product::lockForUpdate()->findOrFail($item['product_id']);

                        if (! $product->active || $product->stock < $quantity) {
                            throw new \RuntimeException("Stock insuficiente para {$product->name}.");
                        }

                        $lineName = $product->name;
                        $unitPrice = (float) $item['unit_price'];
                        $taxRate = (float) $product->tax_rate;
                        $productId = $product->id;
                        $serviceId = null;
                    } else {
                        if (empty($item['service_id'])) {
                            throw new \RuntimeException('Selecciona un servicio.');
                        }

                        $service = Service::findOrFail($item['service_id']);

                        if (! $service->active) {
                            throw new \RuntimeException("El servicio {$service->name} no esta activo.");
                        }

                        $lineName = $service->name;
                        $unitPrice = (float) $item['unit_price'];
                        $taxRate = (float) $service->tax_rate;
                        $productId = null;
                        $serviceId = $service->id;
                        $product = null;
                    }

                    $lineSubtotal = $unitPrice * $quantity;
                    $lineTax = round($lineSubtotal * ($taxRate / 100), 2);
                    $lineTotal = $lineSubtotal + $lineTax;
                    $subtotal += $lineSubtotal;
                    $taxTotal += $lineTax;
                    $items[] = [
                        'item_type' => $item['item_type'],
                        'product' => $product,
                        'product_id' => $productId,
                        'service_id' => $serviceId,
                        'name' => $lineName,
                        'unit_price' => $unitPrice,
                        'tax_rate' => $taxRate,
                        'quantity' => $quantity,
                        'subtotal' => $lineSubtotal,
                        'tax_amount' => $lineTax,
                        'total' => $lineTotal,
                    ];
                }

                if ($discount > $subtotal) {
                    throw new \RuntimeException('El descuento no puede ser mayor que el subtotal.');
                }

                $total = ($subtotal - $discount) + $taxTotal;

                $paidAmount = (float) $data['paid_amount'];
                $isCredit = $data['payment_method'] === 'credito';

                if ($isCredit && empty($data['customer_id'])) {
                    throw new \RuntimeException('Para vender a credito debes seleccionar un cliente.');
                }

                if ($data['invoice_type'] === 'electronica' && empty($data['customer_id'])) {
                    throw new \RuntimeException('Para facturar electrónicamente debes seleccionar un cliente.');
                }

                if (! $isCredit && $paidAmount < $total) {
                    throw new \RuntimeException('El valor pagado es menor que el total.');
                }

                if ($isCredit && $paidAmount > $total) {
                    throw new \RuntimeException('El abono no puede ser mayor que el total.');
                }

                $paymentLines = $this->paymentLines($data, $total, $paidAmount, $isCredit);

                $balance = $isCredit ? $total - $paidAmount : 0;
                $creditStatus = match (true) {
                    ! $isCredit => 'sin_credito',
                    $balance <= 0 => 'pagado',
                    $paidAmount > 0 => 'abonado',
                    default => 'pendiente',
                };

                $sale = Sale::create([
                    'invoice_number' => 'FV-'.now()->format('YmdHis').'-'.auth()->id(),
                    'invoice_type' => $data['invoice_type'],
                    'user_id' => auth()->id(),
                    'customer_id' => $data['customer_id'] ?? null,
                    'subtotal' => $subtotal,
                    'discount' => $discount,
                    'tax' => $taxTotal,
                    'total' => $total,
                    'paid_amount' => $paidAmount,
                    'change_amount' => $isCredit ? 0 : $paidAmount - $total,
                    'balance' => $balance,
                    'credit_due_date' => $isCredit ? $data['credit_due_date'] : null,
                    'credit_status' => $creditStatus,
                    'payment_method' => $data['payment_method'],
                ]);

                foreach ($items as $item) {
                    $sale->items()->create([
                        'product_id' => $item['product_id'],
                        'service_id' => $item['service_id'],
                        'item_type' => $item['item_type'],
                        'product_name' => $item['name'],
                        'unit_price' => $item['unit_price'],
                        'cost_price' => $item['product']
                            ? (float) $item['product']->purchase_price * (1 + ((float) $item['product']->purchase_tax_rate / 100))
                            : 0,
                        'tax_rate' => $item['tax_rate'],
                        'quantity' => $item['quantity'],
                        'subtotal' => $item['subtotal'],
                        'tax_amount' => $item['tax_amount'],
                        'total' => $item['total'],
                    ]);

                    if ($item['product']) {
                        $item['product']->decrement('stock', $item['quantity']);
                    }
                }

                foreach ($paymentLines as $payment) {
                    $sale->payments()->create($payment);
                }

                if ($sale->invoice_type === 'electronica') {
                    $sale->load(['customer', 'items.product', 'items.service']);
                    $response = app(FactusService::class)->issue($sale);
                    $electronic = data_get($response, 'data', []);
                    $sale->update([
                        'electronic_number' => data_get($electronic, 'number'),
                        'electronic_cufe' => data_get($electronic, 'cufe'),
                        'electronic_qr_url' => data_get($electronic, 'qr_url') ?: data_get($electronic, 'public_url'),
                        'electronic_status' => data_get($electronic, 'is_validated') ? 'validada' : (data_get($electronic, 'status') ?: 'registrada'),
                        'electronic_response' => $response,
                    ]);
                }

                return $sale;
            });
        } catch (\RuntimeException $exception) {
            return back()->withInput()->withErrors($exception->getMessage());
        }

        return redirect()->route('sales.receipt', ['sale' => $sale, 'print' => 1])->with('success', 'Venta registrada.');
    }

    public function show(Sale $sale)
    {
        return view('sales.show', ['sale' => $sale->load(['items', 'user', 'customer'])]);
    }

    public function receipt(Sale $sale)
    {
        return view('sales.receipt', ['sale' => $sale->load(['items', 'user', 'customer'])]);
    }

    public function destroy(Sale $sale)
    {
        if ($sale->status === 'anulada') {
            return back()->withErrors('La venta ya esta anulada.');
        }

        app(CashReportService::class)->assertDayIsOpen($sale->created_at);
        DB::transaction(function () use ($sale) {
            $sale->load('items.product');

            foreach ($sale->items as $item) {
                $item->product?->increment('stock', $item->quantity);
            }

            $sale->update(['status' => 'anulada']);
        });

        return back()->with('success', 'Venta anulada y stock restaurado.');
    }

    private function paymentLines(array $data, float $total, float $paidAmount, bool $isCredit): array
    {
        if ($isCredit) {
            return $paidAmount > 0 ? [['payment_method' => $data['credit_payment_method'] ?? 'efectivo', 'amount' => $paidAmount]] : [];
        }

        if ($data['payment_method'] !== 'mixto') {
            return [['payment_method' => $data['payment_method'], 'amount' => $total]];
        }

        $payments = array_values($data['payments'] ?? []);
        if (count($payments) < 2) {
            throw new \RuntimeException('En un pago mixto registra al menos dos medios de pago.');
        }
        $sum = round(array_sum(array_map(fn ($payment) => (float) $payment['amount'], $payments)), 2);
        if (abs($sum - round($total, 2)) > 0.009) {
            throw new \RuntimeException('Los medios de pago deben sumar exactamente el total de la venta.');
        }

        return $payments;
    }

    private function missingElectronicCustomerFields(?Customer $customer): array
    {
        if (! $customer) {
            return ['cliente'];
        }

        $fields = [
            'name' => 'nombre o razón social',
            'document' => 'documento',
            'identification_document_code' => 'tipo de documento',
            'legal_organization_code' => 'tipo de persona',
            'tribute_code' => 'tributo',
            'country_code' => 'país',
            'municipality_code' => 'municipio',
            'address' => 'dirección',
            'email' => 'correo',
            'phone' => 'teléfono',
        ];

        $missing = collect($fields)
            ->filter(fn ($label, $field) => blank($customer->{$field}))
            ->values()
            ->all();

        if (empty($customer->responsibilities)) {
            $missing[] = 'responsabilidades DIAN';
        }

        return $missing;
    }
}
