<?php

namespace App\Http\Controllers;

use App\Models\CreditPayment;
use App\Models\Sale;
use App\Services\CashReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CreditController extends Controller
{
    public function index(Request $request)
    {
        Sale::where('payment_method', 'credito')
            ->where('balance', '>', 0)
            ->whereDate('credit_due_date', '<', now()->toDateString())
            ->whereIn('credit_status', ['pendiente', 'abonado'])
            ->update(['credit_status' => 'vencido']);

        $credits = Sale::with(['customer', 'user', 'creditPayments'])
            ->where('payment_method', 'credito')
            ->when($request->status, fn ($query, $status) => $query->where('credit_status', $status))
            ->when($request->search, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('invoice_number', 'like', "%{$search}%")
                        ->orWhereHas('customer', function ($customer) use ($search): void {
                            $customer->where('name', 'like', "%{$search}%")
                                ->orWhere('document', 'like', "%{$search}%");
                        });
                });
            })
            ->latest()
            ->paginate(12)
            ->withQueryString();

        $summary = Sale::where('payment_method', 'credito')
            ->selectRaw('COALESCE(SUM(total), 0) as total_credit')
            ->selectRaw('COALESCE(SUM(paid_amount), 0) as total_paid')
            ->selectRaw('COALESCE(SUM(balance), 0) as total_balance')
            ->first();

        return view('credits.index', [
            'credits' => $credits,
            'summary' => $summary,
        ]);
    }

    public function payment(Sale $sale)
    {
        abort_unless($sale->payment_method === 'credito', 404);

        return view('credits.payment', ['sale' => $sale->load(['customer', 'creditPayments.user'])]);
    }

    public function storePayment(Request $request, Sale $sale)
    {
        abort_unless($sale->payment_method === 'credito', 404);

        $data = $request->validate([
            'payment_date' => ['required', 'date'],
            'amount' => ['required', 'numeric', 'min:0.01', 'max:'.$sale->balance],
            'payment_method' => ['required', 'in:efectivo,transferencia,tarjeta,otro'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);

        app(CashReportService::class)->assertDayIsOpen($data['payment_date']);

        DB::transaction(function () use ($sale, $data): void {
            $sale = Sale::lockForUpdate()->findOrFail($sale->id);
            $amount = (float) $data['amount'];

            CreditPayment::create($data + [
                'sale_id' => $sale->id,
                'customer_id' => $sale->customer_id,
                'user_id' => auth()->id(),
            ]);

            $newPaidAmount = (float) $sale->paid_amount + $amount;
            $newBalance = max((float) $sale->balance - $amount, 0);

            $sale->update([
                'paid_amount' => $newPaidAmount,
                'balance' => $newBalance,
                'credit_status' => $newBalance <= 0 ? 'pagado' : 'abonado',
            ]);
        });

        return redirect()->route('credits.index')->with('success', 'Abono registrado.');
    }
}
