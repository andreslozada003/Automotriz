<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\CashClosureController;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CreditController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ExpenseController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProductImportController;
use App\Http\Controllers\ProductLookupController;
use App\Http\Controllers\SaleController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SupplierController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/caja', [CashClosureController::class, 'index'])->name('cash.index');
    Route::post('/caja/apertura', [CashClosureController::class, 'open'])->name('cash.open');
    Route::post('/caja/movimientos', [CashClosureController::class, 'movement'])->name('cash.movement');
    Route::post('/caja/cierre', [CashClosureController::class, 'close'])->name('cash.close');

    Route::get('/sales/{sale}/receipt', [SaleController::class, 'receipt'])->name('sales.receipt');
    Route::resource('sales', SaleController::class)->only(['index', 'create', 'store', 'show']);
    Route::resource('customers', CustomerController::class)->only(['index', 'create', 'store', 'edit', 'update']);
    Route::get('/customers/municipalities/search', [CustomerController::class, 'municipalities'])->name('customers.municipalities');
    Route::get('/products/images/{path}', [ProductController::class, 'image'])->where('path', '.*')->name('products.image');
    Route::get('/products/lookup', ProductLookupController::class)->name('products.lookup');
    Route::get('/products/{product}/barcode-label', [ProductController::class, 'barcodeLabel'])->name('products.barcode-label');
    Route::resource('products', ProductController::class)->only(['index']);
    Route::resource('services', ServiceController::class)->only(['index']);

    Route::middleware('role:admin')->group(function () {
        Route::post('/caja/{session}/reabrir', [CashClosureController::class, 'reopen'])->name('cash.reopen');
        Route::post('/caja/cierre-mensual', [CashClosureController::class, 'closeMonthly'])->name('cash.monthly.close');
        Route::get('/products/import', [ProductImportController::class, 'create'])->name('products.import.create');
        Route::post('/products/import', [ProductImportController::class, 'store'])->name('products.import.store');
        Route::get('/products/import/template', [ProductImportController::class, 'template'])->name('products.import.template');
        Route::delete('/sales/{sale}', [SaleController::class, 'destroy'])->name('sales.destroy');
        Route::get('/credits', [CreditController::class, 'index'])->name('credits.index');
        Route::get('/credits/{sale}/payment', [CreditController::class, 'payment'])->name('credits.payment');
        Route::post('/credits/{sale}/payment', [CreditController::class, 'storePayment'])->name('credits.payment.store');
        Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->name('customers.destroy');
        Route::resource('products', ProductController::class)->except(['index', 'show']);
        Route::resource('categories', CategoryController::class)->except(['show']);
        Route::resource('suppliers', SupplierController::class)->except(['show']);
        Route::resource('services', ServiceController::class)->except(['index', 'show']);
        Route::resource('expenses', ExpenseController::class)->except(['show']);
        Route::resource('users', UserController::class)->except(['show']);
    });
});
