{{-- Classic Retail: contact topbar · logo + search + labelled actions · category mega menu --}}
<div class="cl-topbar d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center gap-3">
        <div class="d-flex gap-4">
            @if (setting('store.phone'))<span><i class="ri-phone-line me-1"></i>{{ setting('store.phone') }}</span>@endif
            @if (setting('store.email'))<span><i class="ri-mail-line me-1"></i>{{ setting('store.email') }}</span>@endif
            @if (setting('store.hours'))<span class="d-none d-xl-inline"><i class="ri-time-line me-1"></i>{{ setting('store.hours') }}</span>@endif
        </div>
        <div class="d-flex gap-4">
            <a href="{{ route('track') }}" wire:navigate><i class="ri-map-pin-time-line me-1"></i>Track order</a>
            <a href="{{ route('page', 'shipping') }}" wire:navigate>Shipping info</a>
        </div>
    </div>
</div>
@if ($announce = setting('store.announcement'))
    <div class="sf-announce">{{ $announce }}</div>
@endif
<header class="sf-header">
    <div class="cl-main">
        <div class="container d-flex align-items-center gap-3 gap-lg-5">
            <button class="sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sfMobileMenu" aria-label="Menu"><i class="ri-menu-line"></i></button>
            @include('themes.base.partials.logo', ['mark' => true, 'space' => ' '])
            <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="sf-search d-none d-md-block">
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search for products, brands or phone models" aria-label="Search">
                <button type="submit" aria-label="Search" style="border-radius: 8px"><i class="ri-search-line"></i></button>
            </form>
            <div class="d-flex align-items-center gap-4 ms-auto">
                <a href="{{ store_nav()->customer() ? route('account') : route('login') }}" wire:navigate class="cl-action d-none d-lg-inline-flex"><i class="ri-user-3-line"></i><span>{{ store_nav()->customer() ? 'Welcome back' : 'Sign in' }}<b>Account</b></span></a>
                <a href="{{ route('account.wishlist') }}" wire:navigate class="cl-action d-none d-sm-inline-flex"><i class="ri-heart-line"></i><span class="d-none d-xl-block">Saved<b>Wishlist</b></span></a>
                <button type="button" class="cl-action border-0 bg-transparent p-0" x-data @click="$dispatch('open-cart')">
                    <span class="position-relative"><i class="ri-shopping-cart-line"></i><span class="sf-count" x-show="$store.cart.count > 0" x-text="$store.cart.count" x-cloak></span></span>
                    <span class="d-none d-xl-block text-start">Your cart<b>Checkout</b></span>
                </button>
            </div>
        </div>
    </div>
    <nav class="cl-nav d-none d-lg-block">
        <div class="container d-flex align-items-center gap-2">
            <div class="dropdown">
                <a href="#" class="sf-nav-link cl-browse" data-bs-toggle="dropdown" data-bs-auto-close="outside"><span><i class="ri-apps-2-line me-2"></i>Browse categories</span><i class="ri-arrow-down-s-line"></i></a>
                <div class="dropdown-menu sf-dropdown sf-mega" style="min-width: 760px">
                    <div class="row g-4">
                        @foreach (store_nav()->categories()->chunk(ceil(store_nav()->categories()->count() / 3)) as $chunk)
                            <div class="col-4">
                                @foreach ($chunk as $cat)
                                    <a class="dropdown-item fw-semibold d-flex align-items-center gap-2" href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate><i class="{{ $cat->icon }} text-primary"></i>{{ $cat->name }}</a>
                                    @foreach ($cat->children as $child)
                                        <a class="dropdown-item ps-5 py-1 sf-muted" style="font-size: 14px" href="{{ route('shop', ['category' => $child->slug]) }}" wire:navigate>{{ $child->name }}</a>
                                    @endforeach
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                    <hr style="border-color: var(--sf-border)">
                    <div class="sf-mega-title">Popular phones</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (store_nav()->deviceBrands()->take(3) as $db)
                            @foreach ($db->models->take(3) as $m)
                                <a href="{{ route('shop', ['device' => $m->slug]) }}" wire:navigate class="sf-chip" style="padding: 6px 12px; font-size: 13px">{{ $m->name }}</a>
                            @endforeach
                        @endforeach
                    </div>
                </div>
            </div>
            <a href="{{ route('home') }}" wire:navigate class="sf-nav-link {{ request()->routeIs('home') ? 'is-active' : '' }}">Home</a>
            <a href="{{ route('shop') }}" wire:navigate class="sf-nav-link {{ request()->routeIs('shop') ? 'is-active' : '' }}">Shop</a>
            <a href="{{ route('shop', ['category' => 'screen-protection']) }}" wire:navigate class="sf-nav-link">Screen protection</a>
            <a href="{{ route('shop', ['category' => 'chargers']) }}" wire:navigate class="sf-nav-link">Chargers</a>
            <a href="{{ route('deals') }}" wire:navigate class="sf-nav-link {{ request()->routeIs('deals') ? 'is-active' : '' }}">Deals</a>
            <span class="ms-auto sf-muted fw-semibold" style="font-size: 14px"><i class="ri-truck-line text-primary me-1"></i>Free delivery over {{ money(setting('shipping.free_threshold', 5000)) }}</span>
        </div>
    </nav>
</header>
