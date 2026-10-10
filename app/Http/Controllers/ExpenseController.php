<?php

namespace App\Http\Controllers;

use App\Models\Expense;
use App\Services\CashReportService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ExpenseController extends Controller
{
    public const CATEGORIES = [
        'Gastos administrativos',
        'Servicios publicos',
        'Arriendo',
        'Nomina',
        'Transporte',
        'Combustible',
        'Papeleria',
        'Aseo',
        'Mantenimiento',
        'Reparaciones',
        'Seguridad',
        'Internet y telefonia',
        'Publicidad',
        'Comisiones bancarias',
        'Impuestos',
        'Compras de mercancia',
        'Compras de herramientas',
        'Compra de equipos',
        'Gastos de caja menor',
        'Otros gastos',
    ];

    public function index(Request $request)
    {
        $query = Expense::with('user')
            ->when($request->search, function ($query, $search): void {
                $query->where(function ($query) use ($search): void {
                    $query->where('description', 'like', "%{$search}%")
                        ->orWhere('reference', 'like', "%{$search}%")
                        ->orWhere('notes', 'like', "%{$search}%");
                });
            })
            ->when($request->category, fn ($query, $category) => $query->where('category', $category))
            ->when($request->from, fn ($query, $from) => $query->whereDate('expense_date', '>=', $from))
            ->when($request->to, fn ($query, $to) => $query->whereDate('expense_date', '<=', $to));

        $total = (clone $query)->sum('amount');

        $expenses = $query
            ->latest('expense_date')
            ->latest()
            ->paginate(12)
            ->withQueryString();

        return view('expenses.index', [
            'expenses' => $expenses,
            'categories' => self::CATEGORIES,
            'total' => $total,
        ]);
    }

    public function create()
    {
        return view('expenses.create', ['categories' => self::CATEGORIES]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        app(CashReportService::class)->assertDayIsOpen($data['expense_date']);
        Expense::create($data + ['user_id' => auth()->id()]);

        return redirect()->route('expenses.index')->with('success', 'Gasto registrado.');
    }

    public function edit(Expense $expense)
    {
        return view('expenses.edit', [
            'expense' => $expense,
            'categories' => self::CATEGORIES,
        ]);
    }

    public function update(Request $request, Expense $expense)
    {
        $data = $this->validated($request);
        app(CashReportService::class)->assertDayIsOpen($expense->expense_date);
        app(CashReportService::class)->assertDayIsOpen($data['expense_date']);
        $expense->update($data);

        return redirect()->route('expenses.index')->with('success', 'Gasto actualizado.');
    }

    public function destroy(Expense $expense)
    {
        app(CashReportService::class)->assertDayIsOpen($expense->expense_date);
        $expense->delete();

        return back()->with('success', 'Gasto eliminado.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'expense_date' => ['required', 'date'],
            'category' => ['required', Rule::in(self::CATEGORIES)],
            'description' => ['required', 'string', 'max:255'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_method' => ['required', 'in:efectivo,transferencia,tarjeta,otro'],
            'reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string'],
        ]);
    }
}
