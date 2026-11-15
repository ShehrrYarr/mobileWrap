@php($admin = auth('web')->user())
<header class="app-header">
    <div class="container-fluid">
        <div class="nav-header">
            <div class="header-left hstack gap-3">
                <div class="app-sidebar-logo app-horizontal-logo justify-content-center align-items-center">
                    <a href="{{ route('admin.dashboard') }}" wire:navigate>
                        <span class="fw-bold fs-5 text-body">{{ store_name() }}</span>
                    </a>
                </div>

                <button type="button" class="btn btn-light-light icon-btn sidebar-toggle d-none d-md-block" aria-expanded="false" aria-controls="main-menu">
                    <span class="visually-hidden">Toggle sidebar</span>
                    <i class="ri-menu-2-fill"></i>
                </button>

                <button class="btn btn-light-light icon-btn d-md-none small-screen-toggle" id="smallScreenSidebarLabel" type="button" data-bs-toggle="offcanvas" data-bs-target="#smallScreenSidebar" aria-controls="smallScreenSidebar">
                    <span class="visually-hidden">Sidebar toggle for mobile</span>
                    <i class="ri-arrow-right-fill"></i>
                </button>

                <form class="d-none d-sm-block header-search" action="{{ route('admin.products.index') }}" method="get" onsubmit="event.preventDefault(); Livewire.navigate(this.action + '?search=' + encodeURIComponent(this.q.value));">
                    <div class="form-icon">
                        <input type="search" name="q" class="form-control form-control-icon" placeholder="Search products, SKU or barcode…" aria-label="Search products">
                        <i class="ri-search-2-line text-muted"></i>
                    </div>
                </form>
            </div>

            <div class="header-right hstack gap-3">
                <div class="hstack gap-0 gap-sm-1">
                    <a href="{{ route('home') }}" target="_blank" class="btn btn-sm btn-light-primary d-none d-lg-inline-flex align-items-center gap-1 me-2">
                        <i class="ri-store-2-line"></i> View store
                    </a>

                    <div class="dropdown features-dropdown d-none d-sm-block">
                        <button type="button" class="btn icon-btn btn-text-primary rounded-circle" data-bs-toggle="dropdown" aria-expanded="false">
                            <span class="visually-hidden">Light or dark mode</span>
                            <i class="ri-sun-line fs-20"></i>
                        </button>
                        <div class="dropdown-menu dropdown-menu-end">
                            <div class="dropdown-item cursor-pointer" id="light-theme"><span class="hstack gap-2"><i class="ri-sun-line"></i>Light</span></div>
                            <div class="dropdown-item cursor-pointer" id="dark-theme"><span class="hstack gap-2"><i class="ri-moon-clear-line"></i>Dark</span></div>
                            <div class="dropdown-item cursor-pointer" id="system-theme"><span class="hstack gap-2"><i class="ri-computer-line"></i>System</span></div>
                        </div>
                    </div>

                    <livewire:admin.notifications-bell />
                </div>

                <div class="dropdown profile-dropdown features-dropdown">
                    <button type="button" class="btn profile-btn shadow-none px-0 hstack gap-0 gap-sm-3" data-bs-toggle="dropdown" aria-expanded="false" data-bs-auto-close="outside">
                        <span class="position-relative">
                            <span class="avatar-item avatar avatar-title bg-primary text-white border-0 fs-12 fw-semibold">{{ $admin?->initials() }}</span>
                            <span class="position-absolute border-2 border border-white h-12px w-12px rounded-circle bg-success end-0 bottom-0"></span>
                        </span>
                        <span>
                            <span class="h6 d-none d-xl-inline-block text-start fw-semibold mb-0">{{ $admin?->name }}</span>
                            <span class="d-none d-xl-block fs-12 text-start text-muted">{{ $admin?->roleLabel() }}</span>
                        </span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <span class="dropdown-header fs-12">{{ $admin?->email }}</span>
                        @can('settings.manage')
                            <a class="dropdown-item" href="{{ route('admin.settings') }}" wire:navigate><i class="ri-settings-3-line me-2"></i>Settings</a>
                        @endcan
                        <a class="dropdown-item" href="{{ route('home') }}" target="_blank"><i class="ri-store-2-line me-2"></i>Open storefront</a>
                        <div class="dropdown-divider"></div>
                        <form method="POST" action="{{ route('admin.logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger"><i class="ri-logout-box-r-line me-2"></i>Sign out</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>
