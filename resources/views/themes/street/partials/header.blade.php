{{-- Street Drop: sticker logo, chunky caps nav, marquee announcement --}}
@if ($announce = setting('store.announcement'))
    <div class="sf-announce sf-marquee" style="font-weight: 800; text-transform: uppercase; letter-spacing: .04em">
        <div class="sf-marquee-track">
            @for ($i = 0; $i < 6; $i++)<span>{{ $announce }}</span><span style="color: var(--sf-primary)">✦</span>@endfor
        </div>
    </div>
@endif
<header class="sf-header">
    <div class="container">
        <div class="sf-header-row" style="min-height: 80px">
            <button class="sf-icon-btn d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#sfMobileMenu" aria-label="Menu"><i class="ri-menu-5-line"></i></button>
            @php([$first, $rest] = store_nav()->nameParts())
            <a href="{{ route('home') }}" wire:navigate class="sx-logo">
                @if ($logo = store_nav()->logoUrl())<img src="{{ $logo }}" alt="{{ store_name() }}" style="max-height: 30px">@else{{ $first }}<span class="star">★</span>{{ $rest }}@endif
            </a>
            <nav class="sf-nav sx-nav d-none d-lg-flex ms-4">
                <a href="{{ route('shop', ['sort' => 'new']) }}" wire:navigate class="sf-nav-link">New drops</a>
                <a href="{{ route('shop', ['category' => 'wraps-skins']) }}" wire:navigate class="sf-nav-link">Wraps</a>
                <a href="{{ route('shop', ['category' => 'cases-covers']) }}" wire:navigate class="sf-nav-link">Cases</a>
                <a href="{{ route('shop', ['category' => 'screen-protection']) }}" wire:navigate class="sf-nav-link">Glass</a>
                <a href="{{ route('shop') }}" wire:navigate class="sf-nav-link">Everything</a>
                <a href="{{ route('deals') }}" wire:navigate class="sf-nav-link" style="color: var(--sf-primary-text)">Steals ✦</a>
            </nav>
            <div class="d-flex align-items-center gap-2 ms-auto">
                <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="sf-search d-none d-xl-block" style="width: 220px">
                    <input type="search" name="q" value="{{ request('q') }}" placeholder="Find your phone…" aria-label="Search" style="height: 42px; border: 2.5px solid #111">
                    <button type="submit" aria-label="Search" style="width: 34px; height: 34px; border: 2px solid #111"><i class="ri-search-line"></i></button>
                </form>
                <a href="{{ store_nav()->customer() ? route('account') : route('login') }}" wire:navigate class="sf-icon-btn" aria-label="Account"><i class="ri-user-smile-line"></i></a>
                <button type="button" class="btn btn-ink btn-sm d-inline-flex align-items-center gap-2" x-data @click="$dispatch('open-cart')" aria-label="Cart">
                    <i class="ri-shopping-bag-3-fill"></i> <span class="d-none d-sm-inline">Bag</span>
                    <span class="badge rounded-pill" style="background: var(--sf-accent); color: #111" x-text="$store.cart.count">0</span>
                </button>
            </div>
        </div>
    </div>
</header>
