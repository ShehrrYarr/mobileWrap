{{-- Fallback hero (themes provide their own). --}}
@php($slides = $section->setting('slides', []))
@php($slide = $slides[0] ?? [])
<section class="sf-section">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-6">
                @if (! empty($slide['badge']))<div class="sf-eyebrow">{{ $slide['badge'] }}</div>@endif
                <h1 class="sf-title" style="font-size: clamp(38px, 5vw, 64px)">{{ $slide['title'] ?? store_name() }}</h1>
                <p class="sf-sub fs-5">{{ $slide['subtitle'] ?? '' }}</p>
                @if (! empty($slide['button_text']))
                    <a href="{{ store_url($slide['button_url'] ?? null) }}" wire:navigate class="btn btn-primary btn-lg mt-3">{{ $slide['button_text'] }}</a>
                @endif
            </div>
            <div class="col-lg-6">
                <div class="sf-gallery-main"><img src="{{ ! empty($slide['image']) ? upload_url($slide['image']) : ($data['showcase']->first()?->imageUrl() ?? '') }}" alt=""></div>
            </div>
        </div>
    </div>
</section>
