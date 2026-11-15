{{-- Default header (used by Noir Tech): logo · centred nav · icons, search drops down --}}
@if ($announce = setting('store.announcement'))
    <div class="sf-announce">{{ $announce }}</div>
@endif
<header class="sf-header" x-data="{ search: false }">
    <div class="container">
        <div class="sf-header-row">
            <button class="sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sfMobileMenu" aria-label="Menu"><i class="ri-menu-4-line"></i></button>
            @include('themes.base.partials.logo', ['mark' => true])

            <nav class="sf-nav d-none d-lg-flex mx-auto">
                <a href="{{ route('shop') }}" wire:navigate class="sf-nav-link {{ request()->routeIs('shop') && ! request('category') ? 'is-active' : '' }}">Shop all</a>
                <div class="dropdown">
                    <a href="#" class="sf-nav-link" data-bs-toggle="dropdown" aria-expanded="false">Categories <i class="ri-arrow-down-s-line"></i></a>
                    <div class="dropdown-menu sf-dropdown sf-mega">
                        <div class="row g-4">
                            <div class="col-7">
                                <div class="sf-mega-title">Shop by category</div>
                                <div class="row g-1">
                                    @foreach (store_nav()->categories() as $cat)
                                        <div class="col-6"><a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate><i class="{{ $cat->icon }} opacity-75"></i>{{ $cat->name }}</a></div>
                                    @endforeach
                                </div>
                            </div>
                            <div class="col-5">
                                <div class="sf-mega-title">Shop by phone</div>
                                @foreach (store_nav()->deviceBrands()->take(4) as $db)
                                    @foreach ($db->models->take(2) as $m)
                                        <a class="dropdown-item fs-14" href="{{ route('shop', ['device' => $m->slug]) }}" wire:navigate>{{ $m->full_name }}</a>
                                    @endforeach
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>
                <a href="{{ route('shop', ['category' => 'screen-protection']) }}" wire:navigate class="sf-nav-link">Protection</a>
                <a href="{{ route('shop', ['category' => 'wraps-skins']) }}" wire:navigate class="sf-nav-link">Wraps</a>
                <a href="{{ route('deals') }}" wire:navigate class="sf-nav-link {{ request()->routeIs('deals') ? 'is-active' : '' }}">Deals <span class="badge rounded-pill ms-1" style="background: var(--sf-accent); color: var(--sf-accent-contrast); font-size: 10px">HOT</span></a>
            </nav>

            <div class="d-flex align-items-center gap-1 ms-auto ms-lg-0">
                <button type="button" class="sf-icon-btn" @click="search = !search; $nextTick(() => $refs.q?.focus())" aria-label="Search"><i class="ri-search-line"></i></button>
                <a href="{{ store_nav()->customer() ? route('account') : route('login') }}" wire:navigate class="sf-icon-btn d-none d-sm-inline-flex" aria-label="Account"><i class="ri-user-3-line"></i></a>
                <a href="{{ route('account.wishlist') }}" wire:navigate class="sf-icon-btn d-none d-sm-inline-flex" aria-label="Wishlist"><i class="ri-heart-3-line"></i></a>
                <button type="button" class="sf-icon-btn" x-data @click="$dispatch('open-cart')" aria-label="Cart">
                    <i class="ri-shopping-bag-3-line"></i>
                    <span class="sf-count" x-show="$store.cart.count > 0" x-text="$store.cart.count" x-cloak></span>
                </button>
            </div>
        </div>
    </div>
    <div class="border-top" x-show="search" x-collapse x-cloak style="border-color: var(--sf-border) !important">
        <div class="container py-3">
            <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="sf-search mx-auto" style="max-width: 680px">
                <input type="search" name="q" x-ref="q" value="{{ request('q') }}" placeholder="Search cases, glass, chargers — or your phone model…" aria-label="Search">
                <button type="submit" aria-label="Search"><i class="ri-arrow-right-line"></i></button>
            </form>
        </div>
    </div>
</header>
