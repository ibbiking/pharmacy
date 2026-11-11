<?php

/*
| Routes of the Roadmap 7–12 modules. Included by routes/web.php inside the authenticated admin group (business,
| sales-person, module and permission middleware already apply). Module ownership: config/modules.php 'routes';
| permissions: config/route_permissions.php.
*/

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\Inventory;
use Illuminate\Support\Facades\Route;

// ---- Customers (Core)
Route::get('customers', [CustomerController::class, 'index'])->name('customers.index');
Route::post('customers', [CustomerController::class, 'store'])->name('customers.store');
Route::get('customers/{id}', [CustomerController::class, 'show'])->whereNumber('id')->name('customers.show');
Route::put('customers/{id}', [CustomerController::class, 'update'])->whereNumber('id')->name('customers.update');
Route::delete('customers/{id}', [CustomerController::class, 'destroy'])->whereNumber('id')->name('customers.destroy');
Route::post('customers/{id}/restore', [CustomerController::class, 'restore'])->whereNumber('id')->name('customers.restore');

// ---- Inventory Pro
Route::get('warehouses', [Inventory\WarehouseController::class, 'index'])->name('warehouses.index');
Route::post('warehouses', [Inventory\WarehouseController::class, 'store'])->name('warehouses.store');
Route::put('warehouses/{id}', [Inventory\WarehouseController::class, 'update'])->whereNumber('id')->name('warehouses.update');
Route::delete('warehouses/{id}', [Inventory\WarehouseController::class, 'destroy'])->whereNumber('id')->name('warehouses.destroy');
Route::post('warehouses/{id}/restore', [Inventory\WarehouseController::class, 'restore'])->whereNumber('id')->name('warehouses.restore');
Route::post('warehouses/pos', [Inventory\WarehouseController::class, 'setPos'])->name('warehouses.pos');
foreach (['transfers', 'adjustments'] as $kind) {
    Route::get($kind, [Inventory\StockMoveController::class, 'index'])->name("{$kind}.index");
    Route::get("{$kind}/create", [Inventory\StockMoveController::class, 'create'])->name("{$kind}.create");
    Route::post($kind, [Inventory\StockMoveController::class, 'store'])->middleware('throttle:30,1')->name("{$kind}.store");
    Route::get("{$kind}/{id}", [Inventory\StockMoveController::class, 'show'])->whereNumber('id')->name("{$kind}.show");
}
Route::get('inventory/valuation', Inventory\StockValuationController::class)->name('inventory.valuation');
Route::get('items/{product}/units', Inventory\ItemUnitsController::class)->whereNumber('product')->name('items.units');

// ---- B2B Sales (selling.*) and B2B Purchasing (buying.*): one document engine
use App\Http\Controllers\Admin\Documents\DocumentController;
use App\Http\Controllers\Admin\Documents\PaymentController;

foreach (['selling' => App\Models\Documents\Document::TYPES, 'buying' => App\Models\Documents\Document::TYPES] as $side => $types) {
    foreach ($types as $type => $meta) {
        if (($meta['side'] === 'sales') !== ($side === 'selling')) {
            continue;
        }
        Route::get("{$side}/{$meta['slug']}", [DocumentController::class, 'index'])->name("{$side}.{$meta['slug']}.index");
        Route::get("{$side}/{$meta['slug']}/create", [DocumentController::class, 'create'])->name("{$side}.{$meta['slug']}.create");
        Route::post("{$side}/{$meta['slug']}", [DocumentController::class, 'store'])->middleware('throttle:60,1')->name("{$side}.{$meta['slug']}.store");
    }
    Route::get("{$side}/doc/{id}", [DocumentController::class, 'show'])->whereNumber('id')->name("{$side}.docs.show");
    Route::get("{$side}/doc/{id}/edit", [DocumentController::class, 'edit'])->whereNumber('id')->name("{$side}.docs.edit");
    Route::put("{$side}/doc/{id}", [DocumentController::class, 'update'])->whereNumber('id')->name("{$side}.docs.update");
    Route::post("{$side}/doc/{id}/do/{action}", [DocumentController::class, 'action'])->whereNumber('id')->middleware('throttle:60,1')->name("{$side}.docs.action");
    Route::get("{$side}/doc/{id}/print", [DocumentController::class, 'print'])->whereNumber('id')->name("{$side}.docs.print");
    Route::get("{$side}/payments", [PaymentController::class, 'index'])->name("{$side}.payments.index");
    Route::get("{$side}/payments/create", [PaymentController::class, 'create'])->name("{$side}.payments.create");
    Route::get("{$side}/payments/open-documents", [PaymentController::class, 'openDocuments'])->name("{$side}.payments.open");
    Route::post("{$side}/payments", [PaymentController::class, 'store'])->middleware('throttle:60,1')->name("{$side}.payments.store");
    Route::get("{$side}/payments/{id}", [PaymentController::class, 'show'])->whereNumber('id')->name("{$side}.payments.show");
    Route::delete("{$side}/payments/{id}", [PaymentController::class, 'destroy'])->whereNumber('id')->name("{$side}.payments.destroy");
}
Route::get('buying/doc/{id}/receive', [DocumentController::class, 'receiveForm'])->whereNumber('id')->name('buying.docs.receive-form');
Route::post('buying/doc/{id}/receive', [DocumentController::class, 'receive'])->whereNumber('id')->middleware('throttle:30,1')->name('buying.docs.receive');
Route::get('customers/{id}/statement', [PaymentController::class, 'statement'])->whereNumber('id')->name('customers.statement');
Route::get('suppliers/{id}/statement', [PaymentController::class, 'statement'])->whereNumber('id')->name('suppliers.statement');
Route::get('reports/receivables-aging', [PaymentController::class, 'aging'])->name('reports.ar_aging');
Route::get('reports/payables-aging', [PaymentController::class, 'aging'])->name('reports.ap_aging');

// ---- Accounting
use App\Http\Controllers\Admin\Accounting\AccountController;
use App\Http\Controllers\Admin\Accounting\BooksController;

Route::get('accounting/accounts', [AccountController::class, 'index'])->name('accounting.accounts.index');
Route::post('accounting/accounts', [AccountController::class, 'store'])->name('accounting.accounts.store');
Route::put('accounting/accounts/{id}', [AccountController::class, 'update'])->whereNumber('id')->name('accounting.accounts.update');
Route::delete('accounting/accounts/{id}', [AccountController::class, 'destroy'])->whereNumber('id')->name('accounting.accounts.destroy');
Route::post('accounting/accounts/{id}/restore', [AccountController::class, 'restore'])->whereNumber('id')->name('accounting.accounts.restore');
Route::get('accounting/accounts/{id}/ledger', [BooksController::class, 'ledger'])->whereNumber('id')->name('accounting.ledger');
Route::get('accounting/journals', [BooksController::class, 'journals'])->name('accounting.journals.index');
Route::get('accounting/journals/create', [BooksController::class, 'createJournal'])->name('accounting.journals.create');
Route::post('accounting/journals', [BooksController::class, 'storeJournal'])->name('accounting.journals.store');
Route::get('accounting/journals/{id}', [BooksController::class, 'journal'])->whereNumber('id')->name('accounting.journals.show');
Route::get('accounting/reports', [BooksController::class, 'reports'])->name('accounting.reports');
Route::post('accounting/settings', [BooksController::class, 'settings'])->name('accounting.settings');
Route::post('accounting/backfill', [BooksController::class, 'backfill'])->middleware('throttle:3,10')->name('accounting.backfill');

// ---- Expense
use App\Http\Controllers\Admin\Expense\ExpenseCategoryController;
use App\Http\Controllers\Admin\Expense\ExpenseController;

Route::get('expenses', [ExpenseController::class, 'index'])->name('expenses.index');
Route::get('expenses/create', [ExpenseController::class, 'create'])->name('expenses.create');
Route::post('expenses', [ExpenseController::class, 'store'])->middleware('throttle:60,1')->name('expenses.store');
Route::get('expenses/{id}', [ExpenseController::class, 'show'])->whereNumber('id')->name('expenses.show');
Route::get('expenses/{id}/edit', [ExpenseController::class, 'edit'])->whereNumber('id')->name('expenses.edit');
Route::post('expenses/{id}', [ExpenseController::class, 'update'])->whereNumber('id')->name('expenses.update'); // POST: may carry a receipt file
Route::post('expenses/{id}/do/{action}', [ExpenseController::class, 'action'])->whereNumber('id')->name('expenses.action');
Route::get('expenses/{id}/receipt', [ExpenseController::class, 'receipt'])->whereNumber('id')->name('expenses.receipt');
Route::post('expenses-policy', [ExpenseController::class, 'policy'])->name('expenses.policy');
Route::get('expense-categories', [ExpenseCategoryController::class, 'index'])->name('expense-categories.index');
Route::post('expense-categories', [ExpenseCategoryController::class, 'store'])->name('expense-categories.store');
Route::put('expense-categories/{id}', [ExpenseCategoryController::class, 'update'])->whereNumber('id')->name('expense-categories.update');
Route::delete('expense-categories/{id}', [ExpenseCategoryController::class, 'destroy'])->whereNumber('id')->name('expense-categories.destroy');
Route::post('expense-categories/{id}/restore', [ExpenseCategoryController::class, 'restore'])->whereNumber('id')->name('expense-categories.restore');

// ---- Advanced Reporting
Route::get('analytics', [App\Http\Controllers\Admin\Analytics\AnalyticsController::class, 'index'])->name('analytics.index');
Route::get('analytics/export', [App\Http\Controllers\Admin\Analytics\AnalyticsController::class, 'export'])->middleware('throttle:20,1')->name('analytics.export');
Route::post('analytics/weekly', [App\Http\Controllers\Admin\Analytics\AnalyticsController::class, 'weekly'])->name('analytics.weekly');
