<div class="offcanvas offcanvas-start" tabindex="-1" id="sfMobileMenu" aria-labelledby="sfMobileMenuLabel" style="max-width: 340px">
    <div class="offcanvas-header border-bottom">
        <div id="sfMobileMenuLabel">@include('themes.base.partials.logo', ['mark' => true])</div>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body d-flex flex-column gap-4">
        <form action="{{ route('shop') }}" onsubmit="return sfSearch(this)" class="sf-search mw-100">
            <input type="search" name="q" placeholder="Search products or phone model…" aria-label="Search">
            <button type="submit" aria-label="Search"><i class="ri-search-line"></i></button>
        </form>
        <nav class="d-flex flex-column gap-1">
            <a href="{{ route('shop') }}" wire:navigate class="sf-nav-link">All products</a>
            @foreach (store_nav()->categories() as $cat)
                <a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate class="sf-nav-link"><i class="{{ $cat->icon }} me-2 opacity-75"></i>{{ $cat->name }}</a>
            @endforeach
            <a href="{{ route('deals') }}" wire:navigate class="sf-nav-link"><i class="ri-fire-line me-2 opacity-75"></i>Deals & bundles</a>
            <a href="{{ route('track') }}" wire:navigate class="sf-nav-link"><i class="ri-map-pin-time-line me-2 opacity-75"></i>Track order</a>
        </nav>
        <div class="mt-auto d-grid gap-2">
            @if (store_nav()->customer())
                <a href="{{ route('account') }}" wire:navigate class="btn btn-ink">My account</a>
            @else
                <a href="{{ route('login') }}" wire:navigate class="btn btn-ink">Sign in</a>
                <a href="{{ route('register') }}" wire:navigate class="btn btn-outline-ink">Create account</a>
            @endif
        </div>
    </div>
</div>
