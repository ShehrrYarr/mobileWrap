<?php

use App\Http\Controllers\DashboardController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

/*
|--------------------------------------------------------------------------
| Storefront (landing page = store home)
|--------------------------------------------------------------------------
*/
Route::middleware('storefront')->group(function () {
    Volt::route('/', 'store.home')->name('home');
    Volt::route('/shop', 'store.shop')->name('shop');
    Volt::route('/product/{slug}', 'store.product')->name('product');
    Volt::route('/deals', 'store.deals')->name('deals');
    Volt::route('/cart', 'store.cart')->name('cart');
    Volt::route('/checkout', 'store.checkout')->name('checkout');
    Volt::route('/order/{number}/thank-you', 'store.thank-you')->name('order.thanks');
    Volt::route('/track-order', 'store.track-order')->name('track');
    Volt::route('/page/{slug}', 'store.page')->name('page');

    Route::middleware('guest:customer')->group(function () {
        Volt::route('/login', 'store.auth.login')->name('login');
        Volt::route('/register', 'store.auth.register')->name('register');
    });

    Route::middleware('auth:customer')->prefix('account')->group(function () {
        Volt::route('/', 'store.account.dashboard')->name('account');
        Volt::route('/orders/{number}', 'store.account.order')->name('account.order');
        Volt::route('/wishlist', 'store.account.wishlist')->name('account.wishlist');
        Volt::route('/profile', 'store.account.profile')->name('account.profile');
    });
});

Route::post('/logout', function (Request $request) {
    Auth::guard('customer')->logout();
    $request->session()->regenerateToken();

    return redirect()->route('home');
})->name('logout');

/*
|--------------------------------------------------------------------------
| Admin / back office (Herozi)
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->name('admin.')->group(function () {
    Route::middleware('guest:web')->group(function () {
        Volt::route('/login', 'admin.auth.login')->name('login');
    });

    Route::post('/logout', function (Request $request) {
        Auth::guard('web')->logout();
        $request->session()->forget('preview_theme');
        $request->session()->regenerateToken();

        return redirect()->route('admin.login');
    })->name('logout');

    Route::middleware(['auth:web', 'staff'])->group(function () {
        Volt::route('/', 'admin.dashboard')->name('dashboard');

        Route::middleware('can:catalog.manage')->group(function () {
            Volt::route('/products', 'admin.products.index')->name('products.index');
            Volt::route('/products/create', 'admin.products.form')->name('products.create');
            Volt::route('/products/{product}/edit', 'admin.products.form')->name('products.edit');
            Volt::route('/categories', 'admin.catalog.categories')->name('categories');
            Volt::route('/brands', 'admin.catalog.brands')->name('brands');
            Volt::route('/attributes', 'admin.catalog.attributes')->name('attributes');
            Volt::route('/devices', 'admin.catalog.devices')->name('devices');
        });

        Route::middleware('can:inventory.manage')->group(function () {
            Volt::route('/inventory', 'admin.inventory.index')->name('inventory.index');
            Volt::route('/inventory/{variant}/ledger', 'admin.inventory.ledger')->name('inventory.ledger');
        });

        Route::middleware('can:orders.manage')->group(function () {
            Volt::route('/orders', 'admin.orders.index')->name('orders.index');
            Volt::route('/orders/{order}', 'admin.orders.show')->name('orders.show');
        });

        Volt::route('/customers', 'admin.customers.index')->name('customers.index')->middleware('can:customers.manage');

        Route::middleware('can:marketing.manage')->group(function () {
            Volt::route('/coupons', 'admin.marketing.coupons')->name('coupons');
            Volt::route('/deals', 'admin.marketing.deals')->name('deals');
            Volt::route('/popups', 'admin.marketing.popups')->name('popups');
        });

        Route::middleware('can:storefront.manage')->group(function () {
            Volt::route('/storefront/themes', 'admin.storefront.themes')->name('storefront.themes');
            Volt::route('/storefront/homepage', 'admin.storefront.homepage')->name('storefront.homepage');
        });

        Route::middleware('can:settings.manage')->group(function () {
            Volt::route('/settings', 'admin.settings.general')->name('settings');
            Volt::route('/bank-accounts', 'admin.finance.bank-accounts')->name('bank-accounts');
            Volt::route('/staff', 'admin.staff.index')->name('staff');
        });

        // ── Phase 2: POS, receipts, returns, laser queue, labels ──────────
        Route::middleware('can:pos.use')->group(function () {
            Volt::route('/pos', 'admin.pos.terminal')->name('pos');
            Volt::route('/pos/sales', 'admin.pos.sales')->name('pos.sales');
        });

        Route::get('/receipt/{order}', function (Request $request, \App\Models\Order $order) {
            abort_unless($request->user()->can('pos.use') || $request->user()->can('orders.manage'), 403);
            abort_if($order->status === 'held', 404);

            return view('admin.pos.receipt', ['order' => $order, 'format' => $request->query('format', setting('pos.receipt_format', '80mm'))]);
        })->name('pos.receipt');

        Route::middleware('permission:pos.use|orders.manage')->group(function () {
            Volt::route('/returns', 'admin.returns.index')->name('returns.index');
            Volt::route('/returns/create', 'admin.returns.create')->name('returns.create');
        });

        Volt::route('/laser', 'admin.laser.queue')->name('laser')->middleware('can:laser.manage');

        Route::middleware('permission:inventory.manage|catalog.manage')->group(function () {
            Volt::route('/labels', 'admin.labels.index')->name('labels');
            Route::get('/labels/print', fn () => view('admin.labels.print', ['job' => session('label_job', [])]))->name('labels.print');
        });

        // ── Phase 3: vendors, purchases, khata, bank & cash, expenses ─────
        Route::middleware('can:finance.manage')->group(function () {
            Volt::route('/vendors', 'admin.vendors.index')->name('vendors.index');
            Volt::route('/vendors/{vendor}', 'admin.vendors.show')->name('vendors.show');
            Volt::route('/finance/accounts', 'admin.finance.accounts')->name('finance.accounts');
            Volt::route('/finance/ledger', 'admin.finance.ledger')->name('finance.ledger');
            Volt::route('/finance/expenses', 'admin.finance.expenses')->name('finance.expenses');
        });

        Route::middleware('permission:finance.manage|inventory.manage')->group(function () {
            Volt::route('/purchases', 'admin.purchases.index')->name('purchases.index');
            Volt::route('/purchases/create', 'admin.purchases.form')->name('purchases.create');
            Volt::route('/purchases/{purchase}/edit', 'admin.purchases.form')->name('purchases.edit');
            Volt::route('/purchases/{purchase}', 'admin.purchases.show')->name('purchases.show');
            Route::get('/purchases/{purchase}/print', fn (\App\Models\PurchaseOrder $purchase) => view('admin.print.purchase', ['po' => $purchase->load('items.variant.product', 'vendor', 'user')]))->name('purchases.print');
        });

        Route::middleware('permission:inventory.manage|reports.view')->group(function () {
            Volt::route('/reports/stock-movements', 'admin.reports.stock-movements')->name('reports.stock-movements');
        });

        // Sales / purchase / profit & loss reports with PDF downloads
        Route::middleware('can:reports.view')->prefix('reports')->name('reports.')->group(function () {
            Volt::route('/sales', 'admin.reports.sales')->name('sales');
            Volt::route('/purchases', 'admin.reports.purchases')->name('purchases');
            Volt::route('/profit-loss', 'admin.reports.profit-loss')->name('profit-loss')->middleware('can:cost.view');
            Route::get('/{report}/pdf', [\App\Http\Controllers\Admin\ReportPdfController::class, 'download'])
                ->whereIn('report', ['sales', 'purchases', 'profit-loss'])->name('pdf');
        });

        Volt::route('/customers/{customer}', 'admin.customers.show')->name('customers.show')->middleware('can:customers.manage');

        Route::get('/statements/{type}/{id}', [\App\Http\Controllers\Admin\StatementController::class, 'show'])
            ->whereIn('type', ['customer', 'vendor', 'account'])->name('statements');
        Route::get('/payment-receipt/{entry}', [\App\Http\Controllers\Admin\StatementController::class, 'paymentReceipt'])->name('payment-receipt');

        // Original Herozi demo pages, kept for reference while developing.
        if (app()->isLocal()) {
            Route::get('/demo/{page}', [DashboardController::class, 'index'])->where('page', '[A-Za-z0-9\-]+')->name('demo');
        }
    });
});
