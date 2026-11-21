<?php

/*
 * Platform portal (separate super admin area, docs/SUPER_ADMIN_PORTAL.md). Registered in bootstrap/app.php with the
 * web group but WITHOUT the business app's Inertia props, permissions, modules or business middleware.
 */

use App\Http\Controllers\Platform;
use Illuminate\Support\Facades\Route;

Route::get('login', [Platform\AuthController::class, 'show'])->name('login');
Route::post('login', [Platform\AuthController::class, 'login'])->middleware('throttle:10,1')->name('login.attempt');

Route::middleware('platform.auth')->group(function () {
    Route::post('logout', [Platform\AuthController::class, 'logout'])->name('logout');
    Route::get('/', Platform\DashboardController::class)->name('dashboard');

    $b = Platform\BusinessController::class;
    Route::get('businesses', [$b, 'index'])->name('businesses');
    Route::get('businesses/{id}', [$b, 'show'])->whereNumber('id')->name('businesses.show');
    Route::post('businesses/{id}/modules/{module}', [$b, 'toggleModule'])->whereNumber('id')->name('businesses.module');
    Route::post('businesses/{id}/blocks', [$b, 'block'])->whereNumber('id')->name('businesses.block');
    Route::post('businesses/{id}/open', [$b, 'open'])->whereNumber('id')->name('businesses.open');
    Route::post('leave-business', [$b, 'leave'])->name('leave');

    Route::get('people', [Platform\PeopleController::class, 'users'])->name('people');
    Route::get('people/roles', [Platform\PeopleController::class, 'roles'])->name('roles');
    Route::get('visits', [Platform\VisitsController::class, 'index'])->name('visits');

    $c = Platform\BillingController::class;
    Route::get('billing', [$c, 'index'])->name('billing');
    Route::put('billing/modules/{module}', [$c, 'updateModulePrice'])->name('billing.module-price');
    Route::post('billing/plans', [$c, 'storePlan'])->name('billing.plans.store');
    Route::put('billing/plans/{plan}', [$c, 'updatePlan'])->name('billing.plans.update');
    Route::delete('billing/plans/{plan}', [$c, 'destroyPlan'])->name('billing.plans.destroy');
    Route::put('billing/settings', [$c, 'updateSettings'])->name('billing.settings');
    Route::get('billing/subscriptions', [$c, 'subscriptions'])->name('billing.subscriptions');

    Route::get('settings', [Platform\SettingsController::class, 'show'])->name('settings');
    Route::post('settings', [Platform\SettingsController::class, 'update'])->name('settings.update');

    Route::get('backups', [Platform\BackupController::class, 'index'])->name('backups');
    Route::post('backups', [Platform\BackupController::class, 'create'])->middleware('throttle:3,1')->name('backups.store');
    Route::get('backups/download/{file_name?}', [Platform\BackupController::class, 'download'])->name('backups.download');
    Route::delete('backups/{file_name}', [Platform\BackupController::class, 'destroy'])->name('backups.destroy');

    Route::get('admins', [Platform\AdminController::class, 'index'])->name('admins');
    Route::post('admins', [Platform\AdminController::class, 'store'])->name('admins.store');
    Route::post('admins/{id}/toggle', [Platform\AdminController::class, 'toggle'])->whereNumber('id')->name('admins.toggle');
    Route::delete('admins/{id}', [Platform\AdminController::class, 'destroy'])->whereNumber('id')->name('admins.destroy');
});
