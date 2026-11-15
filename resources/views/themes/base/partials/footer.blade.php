<footer class="sf-footer">
    <div class="container">
        <div class="row g-5">
            <div class="col-lg-4">
                @include('themes.base.partials.logo', ['mark' => true])
                <p class="mt-3 mb-4" style="color: var(--sf-footer-muted); max-width: 340px">{{ setting('store.tagline') }}</p>
                <div class="sf-social">
                    @foreach (['facebook' => 'ri-facebook-fill', 'instagram' => 'ri-instagram-line', 'tiktok' => 'ri-tiktok-fill', 'youtube' => 'ri-youtube-fill'] as $net => $icon)
                        @if ($url = setting('social.'.$net))
                            <a href="{{ $url }}" target="_blank" rel="noopener" aria-label="{{ ucfirst($net) }}"><i class="{{ $icon }}"></i></a>
                        @endif
                    @endforeach
                </div>
            </div>
            <div class="col-6 col-lg-2">
                <div class="sf-footer-title">Shop</div>
                <ul class="sf-footer-links">
                    @foreach (store_nav()->categories()->take(6) as $cat)
                        <li><a href="{{ route('shop', ['category' => $cat->slug]) }}" wire:navigate>{{ $cat->name }}</a></li>
                    @endforeach
                </ul>
            </div>
            <div class="col-6 col-lg-2">
                <div class="sf-footer-title">Help</div>
                <ul class="sf-footer-links">
                    <li><a href="{{ route('track') }}" wire:navigate>Track your order</a></li>
                    <li><a href="{{ route('page', 'shipping') }}" wire:navigate>Shipping</a></li>
                    <li><a href="{{ route('page', 'returns') }}" wire:navigate>Returns & exchanges</a></li>
                    <li><a href="{{ route('page', 'about') }}" wire:navigate>About us</a></li>
                    <li><a href="{{ route('page', 'privacy') }}" wire:navigate>Privacy</a></li>
                </ul>
            </div>
            <div class="col-lg-4">
                <div class="sf-footer-title">Visit or call</div>
                <ul class="sf-footer-links">
                    <li><i class="ri-map-pin-2-line me-2"></i>{{ setting('store.address') }}, {{ setting('store.city') }}</li>
                    @if (setting('store.phone'))<li><i class="ri-phone-line me-2"></i><a href="tel:{{ preg_replace('/[^0-9+]/', '', setting('store.phone')) }}">{{ setting('store.phone') }}</a></li>@endif
                    @if (setting('store.email'))<li><i class="ri-mail-line me-2"></i><a href="mailto:{{ setting('store.email') }}">{{ setting('store.email') }}</a></li>@endif
                    @if (setting('store.hours'))<li><i class="ri-time-line me-2"></i>{{ setting('store.hours') }}</li>@endif
                </ul>
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @if (setting('checkout.cod_enabled', true))<span class="sf-pay-chip"><i class="ri-hand-coin-line"></i> Cash on Delivery</span>@endif
                    @if (setting('checkout.bank_transfer_enabled', true))<span class="sf-pay-chip"><i class="ri-bank-line"></i> Bank Transfer</span>@endif
                    <span class="sf-pay-chip"><i class="ri-truck-line"></i> Nationwide</span>
                </div>
            </div>
        </div>
        <div class="sf-footer-bottom">
            <span>© {{ date('Y') }} {{ store_name() }}. All rights reserved.</span>
            <span><a href="{{ route('page', 'terms') }}" wire:navigate>Terms</a> · <a href="{{ route('page', 'privacy') }}" wire:navigate>Privacy</a></span>
        </div>
    </div>
</footer>
