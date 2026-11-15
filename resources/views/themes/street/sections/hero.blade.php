@php($slide = $section->setting('slides.0', []))
@php($show = $data['showcase'])
@php($lines = array_values(array_filter(preg_split('/(?<=\.)\s+/', $slide['title'] ?? 'Skin it. Wrap it. Own it.'))))
<section class="sx-hero">
    <div class="container">
        <div class="row align-items-center g-5">
            <div class="col-lg-7 position-relative">
                @if (! empty($slide['badge']))<span class="sf-eyebrow">{{ $slide['badge'] }}</span>@endif
                <h1 class="sx-words">
                    @foreach ($lines as $i => $line)<span class="w{{ ($i % 3) + 1 }}">{{ rtrim($line, '.') }}.</span>@endforeach
                </h1>
                @if (! empty($slide['subtitle']))<p class="fs-5 fw-semibold" style="max-width: 520px">{{ $slide['subtitle'] }}</p>@endif
                <div class="d-flex flex-wrap gap-3 mt-4">
                    @if (! empty($slide['button_text']))<a href="{{ $slide['button_url'] ?? route('shop') }}" wire:navigate class="btn btn-primary btn-lg">{{ $slide['button_text'] }} →</a>@endif
                    <a href="{{ route('deals') }}" wire:navigate class="btn btn-outline-ink btn-lg">Steals</a>
                </div>
            </div>
            <div class="col-lg-5">
                <div class="sx-stage">
                    @if (! empty($slide['image']))
                        <div class="sx-phone p2"><img src="{{ upload_url($slide['image']) }}" alt=""></div>
                    @else
                        @foreach ($show->take(2) as $i => $p)
                            <a href="{{ route('product', $p->slug) }}" wire:navigate class="sx-phone p{{ $i + 1 }}"><img src="{{ $p->imageUrl() }}" alt="{{ $p->name }}"></a>
                        @endforeach
                    @endif
                    <span class="sx-sticker round" style="left: 36%; bottom: 2%">100%<br>bubble<br>free</span>
                    <span class="sx-sticker pill" style="right: 0; top: 0">cut in 5 min ✂</span>
                </div>
            </div>
        </div>
    </div>
    <div class="sx-ticker sf-marquee" aria-hidden="true">
        <div class="sf-marquee-track">
            @for ($i = 0; $i < 4; $i++)
                <span>Carbon</span><span class="sep">✦</span><span>Matte</span><span class="sep">✦</span><span>Neon</span><span class="sep">✦</span><span>Marble</span><span class="sep">✦</span><span>Privacy glass</span><span class="sep">✦</span>
            @endfor
        </div>
    </div>
</section>
