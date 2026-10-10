<?php

namespace App\Http\Controllers;

use App\Models\CashClosureAudit;
use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\MonthlyCashClosure;
use App\Services\CashReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CashClosureController extends Controller
{
    public function index(CashReportService $reports)
    {
        $date = now()->startOfDay();
        $session = CashSession::with(['opener', 'closer', 'audits.user'])->whereDate('business_date', $date)->first();

        return view('cash.index', ['date' => $date, 'session' => $session, 'report' => $reports->forPeriod($date, $date, (float) ($session?->opening_balance ?? 0))]);
    }

    public function open(Request $request)
    {
        $data = $request->validate(['opening_balance' => ['required', 'numeric', 'min:0']]);
        $date = now()->toDateString();
        $session = CashSession::firstOrCreate(['business_date' => $date], ['opening_balance' => $data['opening_balance'], 'opened_by' => auth()->id(), 'opened_at' => now(), 'status' => 'open']);
        if (! $session->wasRecentlyCreated) {
            return back()->withErrors('Ya existe una apertura para la fecha de hoy.');
        }
        CashClosureAudit::create(['cash_session_id' => $session->id, 'user_id' => auth()->id(), 'action' => 'opened', 'metadata' => ['opening_balance' => $data['opening_balance']]]);

        return back()->with('success', 'Caja abierta correctamente.');
    }

    public function movement(Request $request, CashReportService $reports)
    {
        $data = $request->validate(['type' => ['required', 'in:income,expense'], 'payment_method' => ['required', 'in:efectivo,transferencia,tarjeta,otro'], 'amount' => ['required', 'numeric', 'min:0.01'], 'concept' => ['required', 'string', 'max:255'], 'notes' => ['nullable', 'string']]);
        $reports->assertDayIsOpen(now());
        CashMovement::create($data + ['movement_date' => now()->toDateString(), 'user_id' => auth()->id()]);

        return back()->with('success', 'Movimiento de caja registrado.');
    }

    public function close(Request $request, CashReportService $reports)
    {
        $data = $request->validate(['counted_cash' => ['required', 'numeric', 'min:0']]);
        DB::transaction(function () use ($data, $reports): void {
            $session = CashSession::whereDate('business_date', now()->toDateString())->lockForUpdate()->first();
            if (! $session) {
                throw ValidationException::withMessages(['cash' => 'Primero debes registrar la apertura de caja.']);
            }
            if ($session->status === 'closed') {
                throw ValidationException::withMessages(['cash' => 'La caja ya fue cerrada.']);
            }
            $report = $reports->forPeriod(now()->startOfDay(), now()->endOfDay(), (float) $session->opening_balance);
            $difference = round((float) $data['counted_cash'] - $report['expected_cash'], 2);
            $session->update(['counted_cash' => $data['counted_cash'], 'expected_cash' => $report['expected_cash'], 'cash_difference' => $difference, 'report_snapshot' => $report + ['counted_cash' => (float) $data['counted_cash'], 'cash_difference' => $difference], 'closed_by' => auth()->id(), 'closed_at' => now(), 'status' => 'closed']);
            CashClosureAudit::create(['cash_session_id' => $session->id, 'user_id' => auth()->id(), 'action' => 'closed', 'metadata' => ['counted_cash' => $data['counted_cash'], 'difference' => $difference]]);
        });

        return back()->with('success', 'Cierre diario confirmado y movimientos bloqueados.');
    }

    public function reopen(Request $request, CashSession $session)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'min:10', 'max:1000']]);
        if ($session->status !== 'closed') {
            return back()->withErrors('Esta caja no está cerrada.');
        }
        $session->update(['status' => 'open', 'closed_by' => null, 'closed_at' => null]);
        CashClosureAudit::create(['cash_session_id' => $session->id, 'user_id' => auth()->id(), 'action' => 'reopened', 'reason' => $data['reason']]);

        return back()->with('success', 'Caja reabierta. El motivo quedó registrado en auditoría.');
    }

    public function closeMonthly(CashReportService $reports)
    {
        abort_unless(auth()->user()->isAdmin(), 403);
        if (now()->day !== 30) {
            return back()->withErrors('El cierre mensual solo se habilita el día 30.');
        }
        $start = now()->startOfMonth();
        $end = now()->endOfMonth();
        if (MonthlyCashClosure::whereDate('period_start', $start)->exists()) {
            return back()->withErrors('Este mes ya tiene un cierre mensual.');
        }
        $report = $reports->forPeriod($start, $end);
        MonthlyCashClosure::create(['period_start' => $start, 'period_end' => $end, 'report_snapshot' => $report, 'closed_by' => auth()->id(), 'closed_at' => now()]);

        return back()->with('success', 'Cierre mensual guardado.');
    }
}
