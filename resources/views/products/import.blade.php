@extends('layouts.app')
@section('title', 'Cargue masivo de productos')
@section('content')
<div class="panel" style="max-width:760px;">
    <h1>Cargue masivo de productos</h1>
    <p class="muted">Descarga la plantilla, llena una fila por producto y súbela. Requiere: <strong>Código, Descripción, Cantidad, Costo neto sin IVA, Margen (%), Categoría y Stock mínimo</strong>. El margen puede ser cualquier valor entre 0 y 999,99. El sistema calcula el precio de venta e IVA automáticamente.</p>
    @if(false)
    <p class="muted">Carga un Excel (.xlsx) o CSV con: <strong>Código, Descripción, Cantidad, Valor Unitario, Precio de Venta, % IVA, Categoría y Stock Mínimo</strong>. El valor unitario será el costo y el producto no tendrá proveedor. Precio de Venta puede quedar vacío. Si la categoría no existe, se creará automáticamente. Cada producto recibe el siguiente código de barras interno.</p>
    @endif
    <p><a class="btn light" href="{{ route('products.import.template') }}" aria-label="Descargar plantilla Excel" title="Descargar plantilla Excel"><x-icon name="download" /> <span>Descargar plantilla Excel</span></a></p>
    <form method="post" action="{{ route('products.import.store') }}" enctype="multipart/form-data">
        @csrf
        <div class="upload-field">
            <input id="productFile" class="upload-input" type="file" name="file" accept=".xlsx,.csv,.txt" required>
            <label for="productFile" class="upload-dropzone" id="uploadDropzone">
                <span class="upload-icon"><x-icon name="upload" /></span>
                <span class="upload-copy"><strong id="uploadTitle">Selecciona tu archivo</strong><span id="uploadDescription">Excel, CSV o TXT · máximo 10 MB</span></span>
                <span class="upload-button">Elegir archivo</span>
            </label>
        </div>
        <div class="actions" style="margin-top:14px;"><button class="btn" aria-label="Importar productos" title="Importar productos"><x-icon name="upload" /> <span>Importar productos</span></button><a class="btn light" href="{{ route('products.index') }}" aria-label="Cancelar" title="Cancelar"><x-icon name="cancel" /> <span>Cancelar</span></a></div>
    </form>
</div>
<style>
    .upload-input { position:absolute; width:1px; height:1px; opacity:0; pointer-events:none; }
    .upload-dropzone { display:flex; align-items:center; gap:14px; padding:18px; border:2px dashed #cbd5e1; border-radius:10px; background:#f8fafc; cursor:pointer; transition:border-color .18s ease, background .18s ease, box-shadow .18s ease; }
    .upload-dropzone:hover, .upload-dropzone:focus-within { border-color:#e50909; background:#fff5f5; box-shadow:0 0 0 4px rgba(229,9,9,.10); }
    .upload-dropzone.has-file { border-color:#15803d; background:#f0fdf4; }
    .upload-icon { display:grid; place-items:center; width:46px; height:46px; flex:0 0 auto; border-radius:9px; color:#fff; background:#111827; }
    .upload-icon .icon { width:22px; height:22px; }
    .upload-copy { display:grid; gap:4px; min-width:0; flex:1; }
    .upload-copy strong { color:#111827; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
    .upload-copy span { color:#64748b; font-size:13px; }
    .upload-button { flex:0 0 auto; padding:9px 12px; border-radius:6px; background:#e50909; color:#fff; font-weight:700; font-size:14px; }
    @media (max-width:560px) { .upload-dropzone { align-items:flex-start; flex-wrap:wrap; } .upload-button { margin-left:60px; } }
</style>
<script>
    (() => {
        const input = document.getElementById('productFile');
        const dropzone = document.getElementById('uploadDropzone');
        const title = document.getElementById('uploadTitle');
        const description = document.getElementById('uploadDescription');
        input.addEventListener('change', () => {
            const file = input.files[0];
            if (!file) return;
            title.textContent = file.name;
            description.textContent = `${(file.size / 1024 / 1024).toFixed(2)} MB · listo para importar`;
            dropzone.classList.add('has-file');
        });
    })();
</script>
@endsection
