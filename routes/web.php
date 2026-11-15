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

        // Original Herozi demo pages, kept for reference while developing.
        if (app()->isLocal()) {
            Route::get('/demo/{page}', [DashboardController::class, 'index'])->where('page', '[A-Za-z0-9\-]+')->name('demo');
        }
    });
});
