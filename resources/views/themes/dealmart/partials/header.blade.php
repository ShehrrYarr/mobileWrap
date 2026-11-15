{{-- Deal Mart: utility bar · logo + big search + actions · red category bar --}}
<div class="dm-top d-none d-md-block">
    <div class="container d-flex justify-content-between align-items-center gap-3">
        <span><i class="ri-megaphone-line me-1"></i>{{ setting('store.announcement') }}</span>
        <span class="d-flex gap-3">
            <a href="{{ route('track') }}" wire:navigate><i class="ri-map-pin-time-line me-1"></i>Track order</a>
            @if (setting('store.phone'))<a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store.phone')) }}"><i class="ri-phone-line me-1"></i>{{ setting('store.phone') }}</a>@endif
        </span>
    </div>
</div>
<header class="sf-header">
    <div class="dm-main">
        <div class="container d-flex align-items-center gap-3 gap-lg-4">
            <button class="sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sfMobileMenu" aria-label="Menu"><i class="ri-menu-line"></i></button>
            @include('themes.base.partials.logo', ['mark' => true, 'space' => ''])
            <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="dm-search d-none d-md-flex mx-auto">
                <select name="category" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach (store_nav()->categories() as $cat)<option value="{{ $cat->slug }}" @selected(request('category') === $cat->slug)>{{ $cat->name }}</option>@endforeach
                </select>
                <input type="search" name="q" value="{{ request('q') }}" placeholder="Search for cases, chargers, iPhone 16…" aria-label="Search">
                <button type="submit"><i class="ri-search-line me-1"></i><span class="d-none d-xl-inline">Search</span></button>
            </form>
            <div class="d-flex align-items-center gap-3 ms-auto ms-md-0">
                <a href="{{ store_nav()->customer() ? route('account') : route('login') }}" wire:navigate class="dm-action d-none d-lg-inline-flex"><i class="ri-user-line"></i><span>{{ store_nav()->customer() ? 'Hello, '.Str::before(store_nav()->customer()->name, ' ') : 'Sign in' }}<b>My account</b></span></a>
                <a href="{{ route('account.wishlist') }}" wire:navigate class="sf-icon-btn d-none d-sm-inline-flex" aria-label="Wishlist"><i class="ri-heart-line"></i></a>
                <button type="button" class="dm-action border-0 bg-transparent p-0" x-data @click="$dispatch('open-cart')" aria-label="Cart">
                    <span class="position-relative"><i class="ri-shopping-cart-2-line"></i><span class="sf-count" x-show="$store.cart.count > 0" x-text="$store.cart.count" x-cloak></span></span>
                    <span class="d-none d-lg-block text-start">Cart<b>View items</b></span>
                </button>
            </div>
        </div>
        <div class="container d-md-none mt-3">
            <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="dm-search"><input type="search" name="q" placeholder="Search products…" aria-label="Search"><button type="submit"><i class="ri-search-line"></i></button></form>
        </div>
    </div>
    <nav class="dm-catbar d-none d-lg-block">
        <div class="container d-flex align-items-center">
            <div class="dropdown">
                <a href="#" class="sf-nav-link dm-allcats" data-bs-toggle="dropdown"><i class="ri-menu-2-line me-1"></i> All categories</a>
                <div class="dropdown-menu sf-dropdown" style="min-width: 260px">
                    @foreach (store_nav()->categories() as $cat)
                        <a class="dropdown-item d-flex align-items-center gap-2" href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate><i class="{{ $cat->icon }}"></i>{{ $cat->name }}</a>
                    @endforeach
                </div>
            </div>
            @foreach (store_nav()->categories()->take(5) as $cat)
                <a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate class="sf-nav-link">{{ $cat->name }}</a>
            @endforeach
            <a href="{{ route('deals') }}" wire:navigate class="sf-nav-link dm-flash-pill ms-auto"><i class="ri-flashlight-fill"></i> Flash Sale</a>
        </div>
    </nav>
</header>
