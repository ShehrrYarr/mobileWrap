<footer class="sx-footer sf-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <h3 class="text-uppercase mb-3" style="font-size: clamp(26px, 3vw, 40px); color: var(--sf-accent)">Your phone.<br>Your rules.</h3>
                <p style="color: var(--sf-footer-muted); max-width: 380px">{{ setting('store.tagline') }}</p>
                <div class="sf-social mt-3">
                    @foreach (['instagram' => 'ri-instagram-line', 'tiktok' => 'ri-tiktok-fill', 'facebook' => 'ri-facebook-fill'] as $net => $icon)
                        @if ($url = setting('social.'.$net))<a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ $net }}"><i class="{{ $icon }}"></i></a>@endif
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="sf-footer-title" style="color: var(--sf-primary)">Shop</div>
                <ul class="sf-footer-links">
                    @foreach (store_nav()->categories()->take(6) as $cat)<li><a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate>{{ $cat->name }}</a></li>@endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="sf-footer-title" style="color: var(--sf-primary)">Help</div>
                <ul class="sf-footer-links">
                    <li><a href="{{ route('track') }}" wire:navigate>Track order</a></li>
                    <li><a href="{{ route('page', 'shipping') }}" wire:navigate>Shipping</a></li>
                    <li><a href="{{ route('page', 'returns') }}" wire:navigate>Returns</a></li>
                    <li><a href="{{ route('page', 'about') }}" wire:navigate>About</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <div class="sf-footer-title" style="color: var(--sf-primary)">Pull up</div>
                <ul class="sf-footer-links">
                    <li>{{ setting('store.address') }}, {{ setting('store.city') }}</li>
                    @if (setting('store.phone'))<li>{{ setting('store.phone') }}</li>@endif
                    <li>{{ setting('store.hours') }}</li>
                </ul>
            </div>
        </div>
        <div class="sx-wordmark" aria-hidden="true">{{ store_name() }} ★</div>
        <div class="sf-footer-bottom">
            <span>© {{ date('Y') }} {{ store_name() }}</span>
            <span>COD ✦ Bank transfer ✦ Nationwide</span>
        </div>
    </div>
</footer>
