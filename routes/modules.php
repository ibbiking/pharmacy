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
