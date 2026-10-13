<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiquetas de códigos de barras</title>
    <style>
        @page { size: A4 portrait; margin: 9mm; }
        * { box-sizing: border-box; }
        body { margin:0; color:#000; font-family:Arial, Helvetica, sans-serif; }
        .toolbar { display:flex; justify-content:space-between; align-items:center; gap:12px; max-width:192mm; margin:12px auto; }
        button { border:0; border-radius:6px; padding:10px 14px; background:#e50909; color:#fff; font:inherit; font-weight:700; cursor:pointer; }
        .labels { display:grid; grid-template-columns:repeat(3, 58mm); gap:4mm 7mm; }
        .label { width:58mm; height:35mm; padding:2mm 3mm; text-align:center; overflow:hidden; page-break-inside:avoid; border:1px dashed #d1d5db; }
        .name { display:flex; align-items:center; justify-content:center; height:9mm; font-size:6.5pt; font-weight:700; line-height:1.12; overflow:hidden; }
        svg { display:block; width:52mm; height:14mm; margin:.5mm auto 0; }
        .reference { margin-top:.5mm; font-size:7pt; letter-spacing:.35px; }
        @media print { .toolbar { display:none; } .label { border:0; } }
    </style>
</head>
<body>
    <div class="toolbar"><strong>{{ $products->count() }} etiquetas listas para imprimir</strong><button type="button" onclick="window.print()">Imprimir etiquetas</button></div>
    <main class="labels">
        @foreach($products as $product)
            <section class="label">
                <div class="name">{{ $product->name }}</div>
                <svg class="barcode" data-code="{{ $product->primaryBarcode->code }}" data-type="{{ $product->primaryBarcode->type }}"></svg>
                <div class="reference">{{ $product->primaryBarcode->code }}</div>
            </section>
        @endforeach
    </main>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        const formats = { UPCA: 'UPC' };
        document.querySelectorAll('.barcode').forEach(element => JsBarcode(element, element.dataset.code, { format: formats[element.dataset.type] || element.dataset.type, displayValue:false, margin:0, height:54, width:1.5 }));
    </script>
</body>
</html>
