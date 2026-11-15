@if ($data['deal'] && $data['products']->isNotEmpty())
    <section class="sf-section">
        <div class="container">
            <div class="dm-flash-head">
                <h3><i class="ri-flashlight-fill"></i> {{ $section->title ?: 'Flash Sale' }}</h3>
                @if ($data['deal']->ends_at)
                    <span class="fw-semibold" style="font-size: 14px">Ending in</span>
                    <div class="sf-countdown" x-data="countdown('{{ $data['deal']->ends_at->toIso8601String() }}')">
                        <div class="sf-cd"><b x-text="h">00</b><small>hrs</small></div><span class="sf-cd-sep">:</span>
                        <div class="sf-cd"><b x-text="m">00</b><small>min</small></div><span class="sf-cd-sep">:</span>
                        <div class="sf-cd"><b x-text="s">00</b><small>sec</small></div>
                    </div>
                @endif
                <a href="{{ route('deals') }}" wire:navigate class="ms-auto fw-bold text-white">Shop all <i class="ri-arrow-right-line"></i></a>
            </div>
            <div class="sf-panel" style="border-radius: 0 0 10px 10px">
                <div class="sf-rail">
                    @foreach ($data['products'] as $product)
                        @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                    @endforeach
                </div>
            </div>
        </div>
    </section>
@endif
