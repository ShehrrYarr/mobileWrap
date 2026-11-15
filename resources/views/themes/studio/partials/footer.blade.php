<footer class="st-footer sf-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-5">
                <p class="sf-display mb-4" style="font-size: clamp(24px, 2.6vw, 34px); font-weight: 300; line-height: 1.2; max-width: 420px">{{ setting('store.tagline') }}</p>
                <p class="sf-muted mb-1">{{ setting('store.address') }}, {{ setting('store.city') }}</p>
                <p class="sf-muted">{{ setting('store.hours') }}</p>
            </div>
            <div class="col-6 col-lg-2">
                <div class="sf-footer-title">Collections</div>
                <ul class="sf-footer-links">
                    @foreach (store_nav()->categories()->take(6) as $cat)<li><a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate>{{ $cat->name }}</a></li>@endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="sf-footer-title">Care</div>
                <ul class="sf-footer-links">
                    <li><a href="{{ route('track') }}" wire:navigate>Track an order</a></li>
                    <li><a href="{{ route('page', 'shipping') }}" wire:navigate>Delivery</a></li>
                    <li><a href="{{ route('page', 'returns') }}" wire:navigate>Returns</a></li>
                    <li><a href="{{ route('page', 'privacy') }}" wire:navigate>Privacy</a></li>
                </ul>
            </div>
            <div class="col-lg-3">
                <div class="sf-footer-title">Contact</div>
                <ul class="sf-footer-links">
                    @if (setting('store.phone'))<li>{{ setting('store.phone') }}</li>@endif
                    @if (setting('store.email'))<li><a href="mailto:{{ setting('store.email') }}">{{ setting('store.email') }}</a></li>@endif
                    @foreach (['instagram', 'facebook', 'tiktok'] as $net)
                        @if ($url = setting('social.'.$net))<li><a href="{{ $url }}" target="_blank" rel="noopener" class="text-capitalize">{{ $net }} ↗</a></li>@endif
                    @endforeach
                </ul>
            </div>
        </div>
        <div class="st-wordmark" aria-hidden="true">{{ store_name() }}</div>
        <div class="sf-footer-bottom mt-4">
            <span>© {{ date('Y') }} {{ store_name() }}</span>
            <span>Cash on Delivery · Bank Transfer · Nationwide delivery</span>
        </div>
    </div>
</footer>
