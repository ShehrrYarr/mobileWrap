@if ($data['deal'] && $data['products']->isNotEmpty())
    <section class="sf-section">
        <div class="container">
            <div class="sf-section-head">
                <div>
                    <div class="sf-eyebrow"><i class="ri-flashlight-fill"></i> {{ $data['deal']->title }} · {{ $data['deal']->discountLabel() }}</div>
                    <h2 class="sf-title">{{ $section->title ?: 'Flash sale' }}</h2>
                    @if ($section->subtitle)<p class="sf-sub">{{ $section->subtitle }}</p>@endif
                </div>
                @if ($data['deal']->ends_at)
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <span class="sf-muted fw-semibold" style="font-size: 13px; text-transform: uppercase; letter-spacing: .08em">Ends in</span>
                        <div class="sf-countdown" x-data="countdown('{{ $data['deal']->ends_at->toIso8601String() }}')">
                            <div class="sf-cd"><b x-text="d">00</b><small>days</small></div><span class="sf-cd-sep">:</span>
                            <div class="sf-cd"><b x-text="h">00</b><small>hrs</small></div><span class="sf-cd-sep">:</span>
                            <div class="sf-cd"><b x-text="m">00</b><small>min</small></div><span class="sf-cd-sep">:</span>
                            <div class="sf-cd"><b x-text="s">00</b><small>sec</small></div>
                        </div>
                    </div>
                @endif
            </div>
            <div class="sf-grid">
                @foreach ($data['products'] as $product)
                    @include(theme_view('partials.product-card'), ['product' => $product, 'wishlist' => $wishlist])
                @endforeach
            </div>
        </div>
    </section>
@endif
