@php($slide = $section->setting('slides.0', []))
@php($show = $data['showcase'])
@php($title = $slide['title'] ?? store_name())
@php($parts = preg_split('/\.\s*/', $title, 2))
<section class="st-hero">
    <div class="container">
        <div class="row align-items-end g-5">
            <div class="col-lg-6">
                <span class="st-index">N° 01</span>
                @if (! empty($slide['badge']))<span class="sf-eyebrow ms-3">{{ $slide['badge'] }}</span>@endif
                <h1 class="st-hero-title">
                    {{ $parts[0] }}@if (count($parts) > 1 && $parts[1] !== '').<br><em>{{ rtrim($parts[1], '.') }}.</em>@elseif (str_ends_with($title, '.')).@endif
                </h1>
                @if (! empty($slide['subtitle']))<p class="st-hero-sub">{{ $slide['subtitle'] }}</p>@endif
                <div class="d-flex flex-wrap gap-4 align-items-center mt-5">
                    @if (! empty($slide['button_text']))
                        <a href="{{ store_url($slide['button_url'] ?? null) }}" wire:navigate class="btn btn-ink btn-lg">{{ $slide['button_text'] }}</a>
                    @endif
                    <a href="{{ route('shop', ['sort' => 'new']) }}" wire:navigate class="sf-link-arrow">New arrivals <i class="ri-arrow-right-line"></i></a>
                </div>
            </div>
            <div class="col-lg-6">
                @php($hero = ! empty($slide['image']) ? upload_url($slide['image']) : $show->first()?->imageUrl())
                <div class="st-arch">@if ($hero)<img src="{{ $hero }}" alt="">@endif</div>
                @if ($p = $show->first())
                    <p class="st-caption">Pictured — {{ $p->name }} · from {{ money($p->priceSummary()['min']) }}</p>
                @endif
            </div>
        </div>
    </div>
</section>
