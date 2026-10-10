@extends('layouts.app')
@section('title', 'Cargue masivo de productos')
@section('content')
<div class="panel" style="max-width:760px;">
    <h1>Cargue masivo de productos</h1>
    <p class="muted">Descarga la plantilla, llena una fila por producto y súbela. Requiere: <strong>Código, Descripción, Cantidad, Costo neto sin IVA, Margen (30, 40, 50 u 80), Categoría y Stock mínimo</strong>. El sistema calcula el precio de venta y aplica IVA del 19% automáticamente.</p>
    @if(false)
    <p class="muted">Carga un Excel (.xlsx) o CSV con: <strong>Código, Descripción, Cantidad, Valor Unitario, Precio de Venta, % IVA, Categoría y Stock Mínimo</strong>. El valor unitario será el costo y el producto no tendrá proveedor. Precio de Venta puede quedar vacío. Si la categoría no existe, se creará automáticamente. Cada producto recibe el siguiente código de barras interno.</p>
    @endif
    <p><a class="btn light" href="{{ route('products.import.template') }}" aria-label="Descargar plantilla Excel" title="Descargar plantilla Excel"><x-icon name="download" /> <span>Descargar plantilla Excel</span></a></p>
    <form method="post" action="{{ route('products.import.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="form-grid">
            <label class="span-2">Archivo Excel o CSV<input type="file" name="file" accept=".xlsx,.csv,.txt" required></label>
        </div>
        <div class="actions" style="margin-top:14px;"><button class="btn" aria-label="Importar productos" title="Importar productos"><x-icon name="upload" /> <span>Importar productos</span></button><a class="btn light" href="{{ route('products.index') }}" aria-label="Cancelar" title="Cancelar"><x-icon name="cancel" /> <span>Cancelar</span></a></div>
    </form>
</div>
@endsection
