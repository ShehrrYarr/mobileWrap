@php
    $menu = [
        ['title' => 'Main'],
        ['label' => 'Dashboard', 'icon' => 'ri-dashboard-3-line', 'route' => 'admin.dashboard', 'can' => 'dashboard.view'],
        ['label' => 'Online Orders', 'icon' => 'ri-shopping-bag-3-line', 'route' => 'admin.orders.index', 'can' => 'orders.manage'],
        ['label' => 'POS', 'icon' => 'ri-computer-line', 'soon' => true, 'can' => 'pos.use'],
        ['label' => 'Laser Cutter Queue', 'icon' => 'ri-scissors-cut-line', 'soon' => true, 'can' => 'laser.manage'],

        ['title' => 'Catalog', 'can' => 'catalog.manage'],
        ['label' => 'Products', 'icon' => 'ri-shopping-basket-2-line', 'can' => 'catalog.manage', 'children' => [
            ['label' => 'All products', 'route' => 'admin.products.index'],
            ['label' => 'Add product', 'route' => 'admin.products.create'],
            ['label' => 'Categories', 'route' => 'admin.categories'],
            ['label' => 'Brands', 'route' => 'admin.brands'],
            ['label' => 'Variant options', 'route' => 'admin.attributes'],
        ]],
        ['label' => 'Device Models', 'icon' => 'ri-smartphone-line', 'route' => 'admin.devices', 'can' => 'catalog.manage'],
        ['label' => 'Inventory', 'icon' => 'ri-archive-stack-line', 'route' => 'admin.inventory.index', 'can' => 'inventory.manage'],

        ['title' => 'People', 'can' => 'customers.manage'],
        ['label' => 'Customers', 'icon' => 'ri-group-line', 'route' => 'admin.customers.index', 'can' => 'customers.manage'],
        ['label' => 'Vendors & Ledgers', 'icon' => 'ri-book-2-line', 'soon' => true, 'can' => 'finance.manage'],

        ['title' => 'Storefront', 'can' => 'storefront.manage'],
        ['label' => 'Themes', 'icon' => 'ri-palette-line', 'route' => 'admin.storefront.themes', 'can' => 'storefront.manage'],
        ['label' => 'Homepage Builder', 'icon' => 'ri-layout-masonry-line', 'route' => 'admin.storefront.homepage', 'can' => 'storefront.manage'],
        ['label' => 'Marketing', 'icon' => 'ri-megaphone-line', 'can' => 'marketing.manage', 'children' => [
            ['label' => 'Deals & bundles', 'route' => 'admin.deals'],
            ['label' => 'Coupons', 'route' => 'admin.coupons'],
            ['label' => 'Popups', 'route' => 'admin.popups'],
        ]],

        ['title' => 'System', 'can' => 'settings.manage'],
        ['label' => 'Settings', 'icon' => 'ri-settings-3-line', 'route' => 'admin.settings', 'can' => 'settings.manage'],
        ['label' => 'Bank Accounts', 'icon' => 'ri-bank-line', 'route' => 'admin.bank-accounts', 'can' => 'settings.manage'],
        ['label' => 'Staff & Roles', 'icon' => 'ri-shield-user-line', 'route' => 'admin.staff', 'can' => 'settings.manage'],
    ];
@endphp

@php($renderMenu = function () use ($menu) { return view('admin.partials.sidebar-menu', ['menu' => $menu])->render(); })

<aside class="app-sidebar">
    <div class="app-sidebar-logo px-6 justify-content-center align-items-center">
        <a href="{{ route('admin.dashboard') }}" wire:navigate class="d-flex align-items-center gap-2 text-decoration-none">
            <span class="mw-logo-mark">MW</span>
            <span class="app-sidebar-logo-default fw-bold fs-5 text-body">{{ store_name() }}</span>
        </a>
    </div>
    <nav class="app-sidebar-menu nav nav-pills flex-column fs-6" id="sidebarMenu" aria-label="Main navigation">
        {!! $renderMenu() !!}
    </nav>
</aside>
<div class="horizontal-overlay"></div>

<div class="offcanvas offcanvas-md offcanvas-start small-screen-sidebar" data-bs-scroll="true" tabindex="-1" id="smallScreenSidebar" aria-labelledby="smallScreenSidebarLabel">
    <div class="offcanvas-header hstack border-bottom">
        <div class="app-sidebar-logo d-flex align-items-center gap-2">
            <span class="mw-logo-mark">MW</span><span class="fw-bold">{{ store_name() }}</span>
        </div>
        <button type="button" class="btn-close bg-transparent" data-bs-dismiss="offcanvas" aria-label="Close"><i class="ri-close-line"></i></button>
    </div>
    <div class="offcanvas-body p-0">
        <aside class="app-sidebar">
            <nav class="app-sidebar-menu nav nav-pills flex-column fs-6" aria-label="Main navigation">
                {!! $renderMenu() !!}
            </nav>
        </aside>
    </div>
</div>
