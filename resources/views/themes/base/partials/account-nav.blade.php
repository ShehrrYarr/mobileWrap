@php($me = auth('customer')->user())
<div class="sf-card-panel">
    <div class="d-flex align-items-center gap-3 mb-3">
        <span class="d-inline-flex align-items-center justify-content-center fw-bold" style="width: 48px; height: 48px; border-radius: 50%; background: var(--sf-primary); color: var(--sf-primary-contrast); font-size: 18px">{{ mb_substr($me->name, 0, 1) }}</span>
        <div class="min-w-0"><b class="d-block text-truncate">{{ $me->name }}</b><span class="sf-muted" style="font-size: 13px">{{ $me->phone }}</span></div>
    </div>
    <nav class="sf-account-nav">
        <a href="{{ route('account') }}" wire:navigate class="{{ request()->routeIs('account', 'account.order') ? 'is-active' : '' }}"><i class="ri-shopping-bag-3-line"></i>Orders</a>
        <a href="{{ route('account.wishlist') }}" wire:navigate class="{{ request()->routeIs('account.wishlist') ? 'is-active' : '' }}"><i class="ri-heart-3-line"></i>Wishlist</a>
        <a href="{{ route('account.profile') }}" wire:navigate class="{{ request()->routeIs('account.profile') ? 'is-active' : '' }}"><i class="ri-user-settings-line"></i>Profile & address</a>
        <form method="POST" action="{{ route('logout') }}">@csrf<button type="submit" class="btn btn-link sf-muted w-100 text-start d-flex align-items-center gap-2 px-3 py-2 text-decoration-none fw-semibold"><i class="ri-logout-box-r-line"></i>Sign out</button></form>
    </nav>
</div>
