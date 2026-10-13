<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Etiqueta {{ $product->name }}</title>
    <style>
        @page { size: 58mm 35mm; margin: 0; }
        * { box-sizing: border-box; }
        body { margin: 0; width: 58mm; font-family: Arial, Helvetica, sans-serif; color: #000; }
        .label { width: 58mm; height: 35mm; padding: 2mm 3mm; text-align: center; overflow: hidden; }
        .name { display:flex; align-items:center; justify-content:center; height:8mm; font-size:8pt; font-weight:700; line-height:1.14; overflow:hidden; }
        svg { display: block; width: 52mm; height: 15mm; margin: 1mm auto 0; }
        .reference { margin-top: .5mm; font-size: 7.5pt; letter-spacing: .4px; }
        .actions { margin-top: 12px; text-align: center; }
        button { border: 0; border-radius: 6px; background: #e50909; color: #fff; padding: 10px 14px; cursor: pointer; font: inherit; }
        @media print { .actions { display: none; } }
    </style>
</head>
<body>
    <main class="label">
        <div class="name">{{ $product->name }}</div>
        <svg id="barcode"></svg>
        <div class="reference">{{ $product->primaryBarcode->code }}</div>
    </main>
    <div class="actions"><button type="button" onclick="window.print()">Imprimir etiqueta</button></div>
    <script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.6/dist/JsBarcode.all.min.js"></script>
    <script>
        const formats = { UPCA: 'UPC' };
        JsBarcode('#barcode', @json($product->primaryBarcode->code), {
            format: formats[@json($product->primaryBarcode->type)] || @json($product->primaryBarcode->type),
            displayValue: false,
            margin: 0,
            height: 54,
            width: 1.5,
        });
    </script>
</body>
</html>
