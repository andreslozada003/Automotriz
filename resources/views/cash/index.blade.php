@extends('layouts.app')
@section('title', 'Cierre de caja')
@section('content')
<div class="actions" style="justify-content:space-between;margin-bottom:14px;"><div><h1>Cierre de caja diario</h1><p class="muted">{{ $date->format('d/m/Y') }} · {{ $session?->status === 'closed' ? 'Caja cerrada' : ($session ? 'Caja abierta' : 'Pendiente de apertura') }}</p></div><button class="btn light" onclick="window.print()">Imprimir reporte</button></div>

@if(! $session)
<form class="panel" method="post" action="{{ route('cash.open') }}"><h2>Apertura del día</h2><p class="muted">Registra el efectivo físico con el que inicia la caja.</p>@csrf <div class="form-grid"><label>Saldo inicial<input required min="0" step="0.01" type="number" name="opening_balance" value="0"></label></div><div class="actions" style="margin-top:14px"><button class="btn">Abrir caja</button></div></form>
@else
<div class="grid grid-4" style="margin-bottom:14px">
    <div class="panel"><div class="muted">Total vendido</div><div class="metric">${{ number_format($report['total_sold'], 0) }}</div><small>Ventas, no recaudo.</small></div>
    <div class="panel"><div class="muted">Ingresos de caja</div><div class="metric">${{ number_format($report['cash_income'], 0) }}</div><small>Ventas, abonos y entradas.</small></div>
    <div class="panel"><div class="muted">Egresos de caja</div><div class="metric">${{ number_format($report['cash_expenses'], 0) }}</div><small>Gastos y salidas.</small></div>
    <div class="panel"><div class="muted">Efectivo esperado</div><div class="metric">${{ number_format($report['expected_cash'], 0) }}</div><small>Saldo inicial + efectivo recibido − pagado.</small></div>
</div>
<div class="grid grid-2" style="margin-bottom:14px">
    <div class="panel"><h2>Utilidad</h2><p>Costos de mercancía vendida: <strong>${{ number_format($report['cost_of_goods'], 0) }}</strong></p><p>Ganancia bruta: <strong>${{ number_format($report['gross_profit'], 0) }}</strong></p>@if($report['cost_pending_items'])<p class="alert error">{{ $report['cost_pending_items'] }} ítems históricos no tienen costo guardado; no se les asignó un valor estimado.</p>@endif</div>
    <div class="panel"><h2>Detalle de recaudo</h2><p>Ventas cobradas: <strong>${{ number_format($report['sales_receipts'], 0) }}</strong></p><p>Abonos a crédito: <strong>${{ number_format($report['credit_receipts'], 0) }}</strong></p><p>Entradas manuales: <strong>${{ number_format($report['manual_income'], 0) }}</strong></p>@if($report['legacy_unclassified_mixed'])<p class="alert error">Hay ${{ number_format($report['legacy_unclassified_mixed'], 0) }} en ventas mixtas antiguas sin desglose; no se atribuyen falsamente a un medio de pago.</p>@endif</div>
</div>

@if($session->status === 'open')
<div class="grid grid-2">
<form class="panel" method="post" action="{{ route('cash.movement') }}">@csrf<h2>Movimiento de caja</h2><div class="form-grid"><label>Tipo<select name="type"><option value="income">Entrada</option><option value="expense">Salida</option></select></label><label>Medio<select name="payment_method"><option value="efectivo">Efectivo</option><option value="transferencia">Transferencia</option><option value="tarjeta">Tarjeta</option><option value="otro">Otro</option></select></label><label>Valor<input type="number" step="0.01" min="0.01" name="amount" required></label><label>Concepto<input name="concept" maxlength="255" required></label><label class="span-2">Notas<textarea name="notes"></textarea></label></div><div class="actions" style="margin-top:14px"><button class="btn secondary">Registrar movimiento</button></div></form>
<form class="panel" method="post" action="{{ route('cash.close') }}">@csrf<h2>Realizar arqueo y confirmar</h2><p class="muted">Cuenta únicamente el efectivo físico. La diferencia se guarda de forma permanente en el reporte.</p><label>Efectivo contado<input type="number" step="0.01" min="0" name="counted_cash" required autofocus></label><p>Efectivo esperado: <strong>${{ number_format($report['expected_cash'], 2) }}</strong></p><div class="actions" style="margin-top:14px"><button class="btn swal-confirm" data-title="¿Confirmar cierre diario?" data-text="Se bloquearán los movimientos de esta fecha." data-confirm="Sí, cerrar caja">Confirmar cierre diario</button></div></form>
</div>
@else
<div class="panel"><h2>Cierre confirmado</h2><p>Efectivo contado: <strong>${{ number_format($session->counted_cash, 2) }}</strong></p><p>Diferencia: <strong>${{ number_format($session->cash_difference, 2) }}</strong></p><p class="muted">Cerrado por {{ $session->closer?->name }} a las {{ $session->closed_at?->format('H:i') }}.</p>@if(auth()->user()->isAdmin())<form method="post" action="{{ route('cash.reopen', $session) }}">@csrf<label>Motivo obligatorio para reabrir<textarea name="reason" minlength="10" required></textarea></label><div class="actions" style="margin-top:14px"><button class="btn danger swal-confirm" data-title="¿Reabrir caja?" data-text="La auditoría conservará este motivo." data-confirm="Sí, reabrir">Reabrir con auditoría</button></div></form>@endif</div>
@endif
@if(auth()->user()->isAdmin())<div class="panel" style="margin-top:14px"><h2>Cierre mensual</h2>@if(now()->day === 30)<form method="post" action="{{ route('cash.monthly.close') }}">@csrf<button class="btn swal-confirm" data-title="¿Guardar cierre mensual?" data-text="Se conservará una copia del reporte del mes." data-confirm="Sí, guardar">Confirmar cierre mensual</button></form>@else<p class="muted">Se habilita únicamente el día 30 de cada mes.</p>@endif</div>@endif
@endif
@endsection
