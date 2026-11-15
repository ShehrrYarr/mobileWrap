{{-- Studio Minimal: centred serif wordmark, small-caps nav underneath --}}
@if ($announce = setting('store.announcement'))
    <div class="sf-announce" style="font-size: 12px; letter-spacing: .08em">{{ $announce }}</div>
@endif
<header class="sf-header st-header">
    <div class="container">
        <div class="sf-header-row" style="min-height: 84px">
            <div class="d-flex align-items-center gap-1" style="flex: 1">
                <button class="sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sfMobileMenu" aria-label="Menu"><i class="ri-menu-line"></i></button>
                <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="d-none d-lg-flex align-items-center gap-2" style="border-bottom: 1px solid var(--sf-border-strong); padding: 4px 0">
                    <i class="ri-search-line sf-muted"></i>
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Search" aria-label="Search" style="border: 0; background: transparent; outline: none; width: 160px; font-size: 14px; color: var(--sf-text)">
                </form>
            </div>
            @include('themes.base.partials.logo', ['space' => ' '])
            <div class="d-flex align-items-center justify-content-end gap-1" style="flex: 1">
                <a href="{{ store_nav()->customer() ? route('account') : route('login') }}" wire:navigate class="sf-icon-btn" aria-label="Account"><i class="ri-user-line"></i></a>
                <a href="{{ route('account.wishlist') }}" wire:navigate class="sf-icon-btn d-none d-sm-inline-flex" aria-label="Wishlist"><i class="ri-heart-line"></i></a>
                <button type="button" class="sf-icon-btn" x-data @click="$dispatch('open-cart')" aria-label="Bag">
                    <i class="ri-handbag-line"></i><span class="sf-count" x-show="$store.cart.count > 0" x-text="$store.cart.count" x-cloak></span>
                </button>
            </div>
        </div>
    </div>
    <nav class="st-nav d-none d-lg-block">
        <div class="container d-flex justify-content-center">
            <a href="{{ route('shop') }}" wire:navigate class="sf-nav-link">Shop</a>
            @foreach (store_nav()->categories()->take(5) as $cat)
                <a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate class="sf-nav-link">{{ $cat->name }}</a>
            @endforeach
            <a href="{{ route('deals') }}" wire:navigate class="sf-nav-link" style="color: var(--sf-accent)">Offers</a>
            <a href="{{ route('page', 'about') }}" wire:navigate class="sf-nav-link">Studio</a>
        </div>
    </nav>
</header>
