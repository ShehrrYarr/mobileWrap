@php($slide = $section->setting('slides.0', []))
@php($show = $data['showcase'])
@php($title = $slide['title'] ?? store_name())
@php($words = explode(' ', $title))
@php($last = array_pop($words))
<section class="nx-hero">
    <div class="nx-grid-bg"></div>
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                @if (! empty($slide['badge']))
                    <span class="nx-badge"><span class="dot"></span>{{ $slide['badge'] }}</span>
                @endif
                <h1 class="nx-hero-title">{{ implode(' ', $words) }} <em>{{ $last }}</em></h1>
                @if (! empty($slide['subtitle']))<p class="nx-hero-sub">{{ $slide['subtitle'] }}</p>@endif
                <div class="d-flex flex-wrap gap-3 mt-4">
                    @if (! empty($slide['button_text']))
                        <a href="{{ $slide['button_url'] ?? route('shop') }}" wire:navigate class="btn btn-primary btn-lg">{{ $slide['button_text'] }} <i class="ri-arrow-right-up-line ms-1"></i></a>
                    @endif
                    <a href="{{ route('shop') }}" wire:navigate class="btn btn-outline-ink btn-lg">Browse all</a>
                </div>
                <div class="nx-stats">
                    <div><b>300+</b><span>device models</span></div>
                    <div><b>5 min</b><span>laser cut time</span></div>
                    <div><b>COD</b><span>nationwide</span></div>
                </div>
            </div>
            <div class="col-lg-6">
                <div class="nx-stage">
                    <div class="nx-stage-ring"></div>
                    @if (! empty($slide['image']))
                        <div class="nx-stage-item i1"><img src="{{ upload_url($slide['image']) }}" alt=""></div>
                    @else
                        @foreach ($show->take(3) as $i => $p)
                            <a href="{{ route('product', $p->slug) }}" wire:navigate class="nx-stage-item i{{ $i + 1 }}"><img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}"></a>
                        @endforeach
                    @endif
                    @if ($first = $show->first())
                        @php($fp = $first->priceSummary())
                        <div class="nx-float-card" style="left: 0; top: 8%"><span class="sf-muted">Trending</span><b>{{ Str::limit($first->name, 22) }}</b><span class="text-primary-sf fw-bold">{{ money($fp['min']) }}</span></div>
                    @endif
                    <div class="nx-float-card" style="right: 2%; bottom: 6%"><span class="sf-muted"><i class="ri-truck-line"></i> Delivery</span><b>{{ setting('shipping.estimated_days', '2–4 days') }}</b></div>
                </div>
            </div>
        </div>
    </div>
    @php($models = \App\Models\DeviceModel::active()->orderBy('sort_order')->limit(14)->pluck('name'))
    <div class="nx-marquee sf-marquee" aria-hidden="true">
        <div class="sf-marquee-track">
            @foreach ([1, 2] as $_)
                @foreach ($models as $m)<span>{{ $m }}</span><span class="sep">✦</span>@endforeach
            @endforeach
        </div>
    </div>
</section>
