<?php

namespace App\Services;

use App\Models\CashMovement;
use App\Models\CashSession;
use App\Models\CreditPayment;
use App\Models\Expense;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\SalePayment;
use Carbon\Carbon;
use Illuminate\Validation\ValidationException;

class CashReportService
{
    public function forPeriod(Carbon $from, Carbon $to, float $openingBalance = 0): array
    {
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();
        $sales = Sale::query()->where('status', 'completada')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]);
        $totalSold = (float) (clone $sales)->sum('total');
        $salesReceipts = (float) SalePayment::query()->whereHas('sale', fn ($query) => $query->where('status', 'completada')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))->sum('amount');
        $legacyReceipts = (float) (clone $sales)->doesntHave('payments')->whereIn('payment_method', ['efectivo', 'transferencia', 'tarjeta'])->sum('total')
            + (float) (clone $sales)->doesntHave('payments')->where('payment_method', 'credito')->sum('paid_amount');
        $salesReceipts += $legacyReceipts;
        $creditReceipts = (float) CreditPayment::whereBetween('payment_date', [$fromDate, $toDate])->sum('amount');
        $expenses = (float) Expense::whereBetween('expense_date', [$fromDate, $toDate])->sum('amount');
        $manualIncome = (float) CashMovement::where('type', 'income')->whereBetween('movement_date', [$fromDate, $toDate])->sum('amount');
        $manualExpense = (float) CashMovement::where('type', 'expense')->whereBetween('movement_date', [$fromDate, $toDate])->sum('amount');
        $cashReceipts = $this->paymentTotal('efectivo', $from, $to);
        $cashExpenses = (float) Expense::where('payment_method', 'efectivo')->whereBetween('expense_date', [$fromDate, $toDate])->sum('amount') + $this->movementTotal('expense', 'efectivo', $fromDate, $toDate);
        $cashIncome = $cashReceipts + $this->movementTotal('income', 'efectivo', $fromDate, $toDate);
        $costData = $this->costOfGoods($from, $to);
        $legacyMixed = (float) (clone $sales)->where('payment_method', 'mixto')->doesntHave('payments')->sum('total');

        return [
            'period_start' => $fromDate, 'period_end' => $toDate,
            'total_sold' => round($totalSold, 2),
            'sales_receipts' => round($salesReceipts, 2),
            'credit_receipts' => round($creditReceipts, 2),
            'manual_income' => round($manualIncome, 2),
            'cash_income' => round($salesReceipts + $creditReceipts + $manualIncome, 2),
            'expenses' => round($expenses, 2), 'manual_expenses' => round($manualExpense, 2),
            'cash_expenses' => round($expenses + $manualExpense, 2),
            'opening_balance' => round($openingBalance, 2), 'cash_received' => round($cashIncome, 2),
            'cash_paid' => round($cashExpenses, 2), 'expected_cash' => round($openingBalance + $cashIncome - $cashExpenses, 2),
            'cost_of_goods' => $costData['amount'], 'cost_pending_items' => $costData['pending_items'],
            'gross_profit' => round($totalSold - $costData['amount'], 2),
            'legacy_unclassified_mixed' => round($legacyMixed, 2),
        ];
    }

    public function assertDayIsOpen(Carbon|string $date): void
    {
        $date = Carbon::parse($date)->toDateString();
        $session = CashSession::whereDate('business_date', $date)->first();
        if ($session?->status === 'closed') {
            throw ValidationException::withMessages(['cash' => 'La caja de esta fecha ya fue cerrada. Solo un administrador puede reabrirla.']);
        }
    }

    private function paymentTotal(string $method, Carbon $from, Carbon $to): float
    {
        $salePayments = (float) SalePayment::where('payment_method', $method)->whereHas('sale', fn ($query) => $query->where('status', 'completada')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]))->sum('amount');
        $legacyCashSales = $method === 'efectivo'
            ? (float) Sale::where('status', 'completada')->doesntHave('payments')->where('payment_method', 'efectivo')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->sum('total')
            : 0;
        $creditPayments = (float) CreditPayment::where('payment_method', $method)->whereBetween('payment_date', [$from->toDateString(), $to->toDateString()])->sum('amount');

        return $salePayments + $legacyCashSales + $creditPayments;
    }

    private function movementTotal(string $type, string $method, string $from, string $to): float
    {
        return (float) CashMovement::where('type', $type)->where('payment_method', $method)->whereBetween('movement_date', [$from, $to])->sum('amount');
    }

    private function costOfGoods(Carbon $from, Carbon $to): array
    {
        $items = SaleItem::whereHas('sale', fn ($query) => $query->where('status', 'completada')->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()]));

        return ['amount' => round((float) (clone $items)->whereNotNull('cost_price')->selectRaw('COALESCE(SUM(cost_price * quantity), 0) as total')->value('total'), 2), 'pending_items' => (int) (clone $items)->whereNull('cost_price')->count()];
    }
}
