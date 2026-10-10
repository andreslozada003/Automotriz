@extends('layouts.app')
@section('title', 'Productos')
@section('content')
<style>
    .product-photo {
        width: 54px;
        height: 54px;
        border-radius: 8px;
        border: 1px solid var(--line);
        background: #f8fafc;
        object-fit: cover;
        transition: transform .18s ease, box-shadow .18s ease;
        transform-origin: left center;
    }
    .product-photo:hover {
        position: relative;
        z-index: 5;
        transform: scale(2.35);
        box-shadow: 0 14px 34px rgba(0,0,0,.25);
    }
    .product-photo-empty {
        width: 54px;
        height: 54px;
        border-radius: 8px;
        border: 1px dashed var(--line);
        display: grid;
        place-items: center;
        color: var(--muted);
        font-size: 12px;
        background: #f8fafc;
    }
</style>
<div class="actions" style="justify-content:space-between;margin-bottom:14px;"><h1>Productos</h1>@if(auth()->user()->isAdmin())<div class="actions"><a class="btn light" href="{{ route('products.import.create') }}" aria-label="Cargue masivo" title="Cargue masivo"><x-icon name="upload" /> <span>Cargue masivo</span></a><a class="btn" href="{{ route('products.create') }}" aria-label="Nuevo producto" title="Nuevo producto"><x-icon name="add" /> <span>Nuevo producto</span></a></div>@endif</div>
<form class="actions" method="get" style="margin-bottom:14px;"><input name="search" placeholder="Buscar por nombre, codigo, SKU o barras" value="{{ request('search') }}" style="max-width:360px;"><button class="btn light icon-btn" aria-label="Buscar" title="Buscar"><x-icon name="search" /></button></form>
<div class="table-responsive"><table><thead><tr><th>Imagen</th><th>Codigo</th><th>SKU</th><th>Barras</th><th>Producto</th><th>Categoria</th><th>Stock</th><th>Precio sin IVA</th><th>IVA</th><th></th></tr></thead><tbody>
@forelse($products as $product)
<tr><td>@if($product->image_url)<img class="product-photo" src="{{ $product->image_url }}" alt="{{ $product->name }}">@else<div class="product-photo-empty">Sin foto</div>@endif</td><td>{{ $product->code }}</td><td>{{ $product->sku ?? 'Pendiente' }}</td><td>{{ $product->primaryBarcode->code ?? 'Pendiente' }}</td><td>{{ $product->name }}</td><td>{{ $product->category->name }}</td><td>@if($product->stock <= $product->min_stock)<span class="badge warn">{{ $product->stock }}</span>@else {{ $product->stock }} @endif</td><td>@if($product->sale_price !== null) ${{ number_format($product->sale_price, 0) }} @else <span class="muted">Sin definir</span> @endif</td><td>{{ number_format($product->tax_rate, 2) }}%</td><td class="actions">@if($product->primaryBarcode)<a class="btn secondary icon-btn" target="_blank" rel="noopener" href="{{ route('products.barcode-label', $product) }}" aria-label="Imprimir barras" title="Imprimir barras"><x-icon name="print" /></a>@endif @if(auth()->user()->isAdmin())<a class="btn light icon-btn" href="{{ route('products.edit', $product) }}" aria-label="Editar producto" title="Editar producto"><x-icon name="edit" /></a><form method="post" action="{{ route('products.destroy', $product) }}">@csrf @method('delete')<button class="btn danger icon-btn swal-confirm" aria-label="Eliminar producto" title="Eliminar producto" data-title="Eliminar producto?" data-text="Solo se eliminara si no tiene ventas registradas." data-confirm="Si, eliminar"><x-icon name="delete" /></button></form>@elseif(! $product->primaryBarcode) <span class="muted">Sin código</span> @endif</td></tr>
@empty <tr><td colspan="10" class="muted">No hay productos.</td></tr> @endforelse
</tbody></table></div><div class="pagination">{{ $products->links() }}</div>
@endsection
