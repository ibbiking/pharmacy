<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\Admin\BackupController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\LogoutController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\Auth\RegisterController;
use App\Http\Controllers\Admin\Auth\ResetPasswordController;
use App\Http\Controllers\Admin\Auth\ForgotPasswordController;
use App\Http\Controllers\Admin\CategoryController;
use App\Http\Controllers\Admin\CompanyController;
use App\Http\Controllers\Admin\ProductTypeController;
use App\Http\Controllers\Admin\StrengthController;
use App\Http\Controllers\Admin\FarmulaController;
use App\Http\Controllers\Admin\PermissionController;
use App\Http\Controllers\Admin\ProductController;
use App\Http\Controllers\Admin\POSController;
use App\Http\Controllers\Admin\PurchaseController;
use App\Http\Controllers\Admin\RoleController;
use App\Http\Controllers\Admin\SaleController;
use App\Http\Controllers\Admin\SupplierController;
use App\Http\Controllers\Admin\GenericProductController;
use App\Http\Controllers\GenerateQrWithLogoController;
use App\Http\Controllers\Admin\ProductCategoryController;
use App\Http\Controllers\Admin\TaxController;
use App\Http\Controllers\Admin\InvoiceController;
use App\Http\Controllers\Admin\ReportController;
use App\Http\Controllers\Admin\PharmacyController;
use App\Http\Controllers\Admin\ReturnController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::middleware(['auth'])->prefix('admin')->group(function () {
    // Create-business wizard: reachable without a business (first login) and any time later.
    Route::redirect('business/setup', '/admin/business/create')->name('business.setup');
    Route::get('business/create', [App\Http\Controllers\Admin\BusinessOnboardingController::class, 'create'])->name('business.create');
    Route::post('business/create', [App\Http\Controllers\Admin\BusinessOnboardingController::class, 'store'])->name('business.store');
    Route::get('business/create/currencies', [App\Http\Controllers\Admin\BusinessOnboardingController::class, 'currencies'])->name('business.create.currencies');
    Route::post('preferences/theme', [App\Http\Controllers\Admin\UserPreferenceController::class, 'theme'])->name('preferences.theme');
    
    Route::middleware([
        \App\Http\Middleware\EnsureUserHasBusiness::class,
        \App\Http\Middleware\RestrictSalesPersonAccess::class,
        \App\Http\Middleware\EnsureModuleActive::class,
    ])->group(function () {
    Route::get('business/settings', [App\Http\Controllers\Admin\BusinessController::class, 'settings'])->name('business.settings');
    Route::post('business/switch', [App\Http\Controllers\Admin\UserPreferenceController::class, 'switchBusiness'])->name('business.switch');
    Route::get('lookup/{entity}', App\Http\Controllers\Admin\LookupController::class)->name('lookup');
    Route::middleware('role:super-admin')->prefix('superadmin/billing')->name('superadmin.billing')->group(function () {
        $c = App\Http\Controllers\Admin\SuperAdminBillingController::class;
        Route::get('/', [$c, 'index']);
        Route::put('modules/{module}', [$c, 'updateModulePrice'])->name('.module-price');
        Route::post('plans', [$c, 'storePlan'])->name('.plans.store');
        Route::put('plans/{plan}', [$c, 'updatePlan'])->name('.plans.update');
        Route::delete('plans/{plan}', [$c, 'destroyPlan'])->name('.plans.destroy');
        Route::put('settings', [$c, 'updateSettings'])->name('.settings');
    });
    Route::prefix('settings/email')->name('settings.email')->group(function () {
        $c = App\Http\Controllers\Admin\EmailSettingsController::class;
        Route::get('/', [$c, 'index']);
        Route::put('/', [$c, 'update'])->name('.update');
        Route::post('test', [$c, 'test'])->name('.test')->middleware('throttle:6,1');
        Route::get('preview', [$c, 'preview'])->name('.preview');
        Route::post('branding', [$c, 'branding'])->name('.branding');
    });
    Route::get('settings/numbering', [App\Http\Controllers\Admin\NumberingController::class, 'index'])->name('settings.numbering');
    Route::put('settings/numbering/{type}', [App\Http\Controllers\Admin\NumberingController::class, 'update'])->name('settings.numbering.update');
    Route::prefix('settings/roles')->name('settings.roles')->group(function () {
        $c = App\Http\Controllers\Admin\RoleManagementController::class;
        Route::get('/', [$c, 'index']);
        Route::post('/', [$c, 'store'])->name('.store');
        Route::put('{role}', [$c, 'update'])->name('.update');
        Route::delete('{role}', [$c, 'destroy'])->name('.destroy');
    });
    Route::prefix('settings/team')->name('settings.team')->group(function () {
        $c = App\Http\Controllers\Admin\TeamController::class;
        Route::get('/', [$c, 'index']);
        Route::post('/', [$c, 'invite'])->name('.invite')->middleware('throttle:20,1');
        Route::put('{member}', [$c, 'update'])->name('.update');
        Route::delete('{member}', [$c, 'remove'])->name('.remove');
    });
    Route::get('help', [App\Http\Controllers\Admin\HelpController::class, 'index'])->name('help.index');
    Route::get('help/{topic}', [App\Http\Controllers\Admin\HelpController::class, 'show'])->name('help.show');
    Route::get('modules', [App\Http\Controllers\Admin\ModuleController::class, 'index'])->name('modules.index');
    Route::post('modules/{module}', [App\Http\Controllers\Admin\ModuleController::class, 'activate'])->name('modules.activate');
    Route::delete('modules/{module}', [App\Http\Controllers\Admin\ModuleController::class, 'deactivate'])->name('modules.deactivate');
    Route::post('business/settings', [App\Http\Controllers\Admin\BusinessController::class, 'updateSettings'])->name('business.settings.update');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('', [DashboardController::class, 'Index']);
    Route::get('notification', [NotificationController::class, 'markAsRead'])->name('mark-as-read');
    Route::get('notification-read', [NotificationController::class, 'read'])->name('read');
    Route::get('profile', [UserController::class, 'profile'])->name('profile');
    Route::post('profile/{user}', [UserController::class, 'updateProfile'])->name('profile.update');
    Route::put('profile/update-password/{user}', [UserController::class, 'updatePassword'])->name('update-password');
    Route::post('logout', [LogoutController::class, 'index'])->name('logout');

    Route::resource('users', UserController::class);
    Route::resource('permissions', PermissionController::class)->only(['index', 'store', 'destroy']);
    Route::put('permission', [PermissionController::class, 'update'])->name('permissions.update');
    Route::resource('roles', RoleController::class);
    Route::get('suppliers/autocomplete-name', [SupplierController::class, 'autocompleteName'])->name('suppliers.autocomplete');
    Route::resource('suppliers', SupplierController::class);
    Route::get('categories/autocomplete-name', [CategoryController::class, 'autocompleteName'])->name('categories.autocomplete');
    Route::resource('categories', CategoryController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('companies/autocomplete-name', [CompanyController::class, 'autocompleteName'])->name('companies.autocomplete');
    Route::resource('companies', CompanyController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('product-types/autocomplete-name', [ProductTypeController::class, 'autocompleteName'])->name('product-types.autocomplete');
    Route::resource('product-types', ProductTypeController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('strengths/autocomplete-name', [StrengthController::class, 'autocompleteName'])->name('strengths.autocomplete');
    Route::resource('strengths', StrengthController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('farmulas/autocomplete-name', [FarmulaController::class, 'autocompleteName'])->name('farmulas.autocomplete');
    Route::resource('farmulas', FarmulaController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('pharmacies/autocomplete-name', [PharmacyController::class, 'autocompleteName'])->name('pharmacies.autocomplete');
    Route::resource('pharmacies', PharmacyController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('purchases/create-multiple', [PurchaseController::class, 'createMultiple'])->name('purchases.create-multiple');
    Route::post('purchases/store-multiple', [PurchaseController::class, 'storeMultiple'])->name('purchases.store-multiple');
    Route::get('purchase/category-price', [PurchaseController::class, 'getPurchaseCategoryPrice'])->name('purchases.category-price');
    Route::resource('purchases', PurchaseController::class)->except('show');
    Route::get('purchases/reports', [PurchaseController::class, 'reports'])->name('purchases.report');
    Route::post('purchases/reports', [PurchaseController::class, 'generateReport']);
    Route::get('products/drafts', [ProductController::class, 'drafts'])->name('products.drafts');
    Route::get('products/{product}/setup-wizard', [ProductController::class, 'setupWizard'])->name('products.setup-wizard');
    Route::get('products/{id}/details', [ProductController::class, 'details'])->name('products.details');
    Route::get('products/{product}/quick-stock', [ProductController::class, 'quickStockModal'])->name('products.quick-stock');
    Route::get('products/autocomplete-name', [ProductController::class, 'autocompleteName'])->name('products.autocomplete');
    Route::resource('products', ProductController::class)->except('show');
    Route::get('products/outstock', [ProductController::class, 'outstock'])->name('outstock');
    Route::get('products/expired', [ProductController::class, 'expired'])->name('expired');

    // Super Admin Business Management & Impersonation
    Route::get('superadmin/businesses', [\App\Http\Controllers\Admin\SuperAdminBusinessController::class, 'index'])->name('superadmin.businesses');
    Route::get('superadmin/businesses/{id}/impersonate', [\App\Http\Controllers\Admin\SuperAdminBusinessController::class, 'impersonate'])->name('superadmin.businesses.impersonate');
    Route::get('superadmin/businesses/stop-impersonating', [\App\Http\Controllers\Admin\SuperAdminBusinessController::class, 'stopImpersonating'])->name('superadmin.businesses.stop');

    // Generic Products Routes
    Route::get('generic-products/autocomplete-name', [GenericProductController::class, 'autocompleteName'])->name('generic_products.autocomplete');
    Route::get('generic-products/sync-all', [GenericProductController::class, 'syncAll'])->name('generic_products.syncAll');
    Route::get('generic-products', [GenericProductController::class, 'index'])->name('generic_products.index');
    Route::get('generic-products/suggestions', [GenericProductController::class, 'suggestions'])->name('generic_products.suggestions');
    Route::post('generic-products/suggestions/bulk-approve', [GenericProductController::class, 'bulkApprove'])->name('generic_products.bulkApprove');
    Route::get('generic-products/suggest', [GenericProductController::class, 'suggest'])->name('generic_products.suggest');
    Route::get('generic-products/{id}/edit', [GenericProductController::class, 'edit'])->name('generic_products.edit');
    Route::put('generic-products/{id}', [GenericProductController::class, 'update'])->name('generic_products.update');
    Route::post('generic-products/suggest', [GenericProductController::class, 'storeSuggestion'])->name('generic_products.suggest.store');
    Route::post('generic-products/import', [GenericProductController::class, 'import'])->name('generic_products.import');
    Route::post('generic-products/bulk-import', [GenericProductController::class, 'bulkImport'])->name('generic_products.bulkImport');
    Route::post('generic-products/import-all', [GenericProductController::class, 'importAll'])->name('generic_products.importAll');
    Route::get('generic-products/{id}/details', [GenericProductController::class, 'details'])->name('generic_products.details');
    
    // Generic Setup Wizard endpoints
    Route::get('generic-products/{id}/setup-wizard', [GenericProductController::class, 'setupWizard'])->name('generic_products.setupWizard');
    Route::post('generic-product-categories/store', [GenericProductController::class, 'storeCategory'])->name('generic-product-categories.store');
    Route::delete('generic-product-categories/{id}/destroy', [GenericProductController::class, 'destroyCategory'])->name('generic-product-categories.destroy');
    Route::post('generic-products/{id}/parameters', [GenericProductController::class, 'storeParameters'])->name('generic-products.parameters.store');
    Route::get('generic-products/{id}/approve', [GenericProductController::class, 'approve'])->name('generic_products.approve');
    Route::get('generic-products/{id}/reject', [GenericProductController::class, 'reject'])->name('generic_products.reject');

    Route::resource('sales', SaleController::class)->except('show');

    Route::get('backup', [BackupController::class, 'index'])->name('backup.index');
    Route::put('backup/create', [BackupController::class, 'create'])->name('backup.store');
    Route::get('backup/download/{file_name?}', [BackupController::class, 'download'])->name('backup.download');
    Route::delete('backup/delete/{file_name?}', [BackupController::class, 'destroy'])->where('file_name', '(.*)')->name('backup.destroy');

    Route::get('settings', [SettingController::class, 'index'])->name('settings');
    Route::get('products/{product}/parameters', [ProductController::class, 'parameters'])->name('products.parameters');
    Route::post('products/{product}/parameters', [ProductController::class, 'storeParameters'])->name('products.parameters.store');
    Route::post('/products/parameters/save', [ProductController::class, 'storeParameters'])
        ->name('products.parameters.save');

    Route::get('/categories/children/{id}', [ProductController::class, 'getChildCategories']);
    Route::resource('product-categories', ProductCategoryController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::resource('taxes', TaxController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::resource('currencies', \App\Http\Controllers\Admin\CurrencyController::class)->only(['index', 'store', 'destroy', 'create', 'edit', 'update']);
    Route::get('/products/{id}/stock-summary', [ProductController::class, 'stockSummary'])->name('products.stock-summary');
    Route::get('/products/{id}/pos-availability', [ProductController::class, 'posAvailability'])->name('products.pos.availability');
    Route::get('/products/{id}/price-summary', [ProductController::class, 'priceSummary'])->name('products.price-summary');
    Route::get('/product/{id}/categories', [ProductController::class, 'getProductCategories']);
    Route::get('products/{product}/sale-price-preferences', [ProductController::class, 'salePricePreferences'])
        ->name('products.sale-price-preferences');
    Route::post('products/{product}/sale-price-preferences', [ProductController::class, 'storeSalePricePreferences'])
        ->name('products.sale-price-preferences.store');
    Route::get('global-sale-price-preferences', [ProductController::class, 'globalSalePricePreferences'])
        ->name('global-sale-price-preferences.index');
    Route::post('global-sale-price-preferences', [ProductController::class, 'storeGlobalSalePricePreferences'])
        ->name('global-sale-price-preferences.store');
    Route::resource('pos', POSController::class)
        ->only(['index', 'create', 'store', 'edit', 'update', 'destroy']);
    Route::get('/products/search', [ProductController::class, 'search'])->name('products.search');
    Route::get('/products/category-price', [ProductController::class, 'getCategoryPrice'])->name('products.category-price');
    Route::post('/products/pos/check-stock', [ProductController::class, 'handlePOSQuantityChange'])->name('products.pos.checkStock');
    Route::get('/pos/product-discount-info/{id}', [PosController::class, 'getProductDiscountInfo']);
    Route::get('/pos/next-invoice-number', [PosController::class, 'nextInvoiceNumber'])->name('pos.next-invoice-number');
    Route::post('/pos/cart-tax-breakdown', [PosController::class, 'cartTaxBreakdown'])->name('pos.cart-tax-breakdown');
    Route::post('/pos/print-receipt', [PosController::class, 'printReceipt'])->name('pos.print-receipt');
    Route::post('/pos/save-invoice', [PosController::class, 'saveInvoice'])->name('pos.save-invoice');
    Route::post('/pos/save-cart-session', [POSController::class, 'saveCartSession'])
    ->name('pos.save-cart-session');

    Route::group(['prefix' => 'reports'], function () {    // Reports logic
        Route::get('stock-indication', [\App\Http\Controllers\Admin\StockIndicationReportController::class, 'index'])->name('reports.stock_indication');
        Route::get('stock-unaligned', [\App\Http\Controllers\Admin\StockIndicationReportController::class, 'unaligned'])->name('reports.stock_unaligned');
        Route::get('sales', [ReportController::class, 'sales'])->name('reports.sales');
        Route::get('profit-loss', [ReportController::class, 'profitLoss'])->name('reports.profit_loss');
        Route::get('expiry', [ReportController::class, 'expiry'])->name('reports.expiry');
        Route::post('expiry', [ReportController::class, 'expiry'])->name('reports.expiry.post');
        Route::get('returns', [ReturnController::class, 'report'])->name('reports.returns');
    });

    Route::group(['prefix' => 'invoices'], function () {

        Route::get('/', [InvoiceController::class, 'index'])->name('invoices.index');

        Route::get('/{invoice_no}', [InvoiceController::class, 'show'])->name('invoices.show');
        Route::get('/{invoice_no}/print', [InvoiceController::class, 'printInvoice'])->name('invoices.print');

        // Invoice & Returns
        Route::post('/{invoice_no}/return', [InvoiceController::class, 'returnInvoice'])->name('invoices.return');
        Route::post('/{invoice_no}/return-item/{item_id}', [InvoiceController::class, 'returnProduct'])->name('invoices.return-product');
        Route::post('/print-return-receipt', [InvoiceController::class, 'printReturnReceipt'])->name('invoices.print-return-receipt');
    });

    Route::group(['prefix' => 'returns'], function () {
        Route::get('/{return_no}', [ReturnController::class, 'show'])->name('returns.show');
        Route::get('/{return_no}/print', [ReturnController::class, 'printReturn'])->name('returns.print');
    });
    });
});

Route::middleware(['guest'])->prefix('admin')->group(function () {
    Route::get('', [DashboardController::class, 'Index']);

    Route::get('login', [LoginController::class, 'index'])->name('login');
    Route::post('login', [LoginController::class, 'login']);

    Route::get('register', [RegisterController::class, 'index'])->name('register');
    Route::post('register', [RegisterController::class, 'store']);

    Route::get('signup', [App\Http\Controllers\Admin\Auth\SignupController::class, 'showSignupForm'])->name('signup');
    Route::post('signup', [App\Http\Controllers\Admin\Auth\SignupController::class, 'processSignup']);
    Route::get('verify-email', [App\Http\Controllers\Admin\Auth\SignupController::class, 'verifyEmail'])->name('verification.verify');
    Route::post('complete-registration', [App\Http\Controllers\Admin\Auth\SignupController::class, 'completeRegistration'])->name('register.complete');

    Route::get('forgot-password', [ForgotPasswordController::class, 'index'])->name('password.request');
    Route::post('forgot-password', [ForgotPasswordController::class, 'requestEmail'])->name('password.email');
    Route::get('forgot-password/verify', [ForgotPasswordController::class, 'showVerifyForm'])->name('password.verify.form');
    Route::post('forgot-password/verify', [ForgotPasswordController::class, 'verifyCode'])->name('password.verify');
    Route::post('forgot-password/resend', [ForgotPasswordController::class, 'resendCode'])->name('password.resend');
    Route::get('reset-password', [ResetPasswordController::class, 'index'])->name('password.reset.form');
    Route::post('reset-password', [ResetPasswordController::class, 'resetPassword'])->name('password.update');
});

Route::get('/', function () {
    return view('welcome');
});

Route::get('/qr-logo', [GenerateQrWithLogoController::class, 'generateQrWithLogo']);