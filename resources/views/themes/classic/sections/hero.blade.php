@php($slides = $section->setting('slides', []))
@php($show = $data['showcase'])
<section>
    <div class="container">
        <div class="cl-hero" x-data="slider({{ max(count($slides), 1) }}, 6500)">
            @foreach ($slides as $i => $s)
                @php($img = ! empty($s['image']) ? upload_url($s['image']) : $show->get($i)?->imageUrl())
                <div class="cl-slide" x-show="i === {{ $i }}" x-transition:enter="transition ease-out duration-500" x-transition.opacity @if ($i) x-cloak @endif>
                    <div>
                        @if (! empty($s['badge']))<span class="cl-slide-tag"><i class="ri-shield-check-fill"></i>{{ $s['badge'] }}</span>@endif
                        <h1>{{ $s['title'] ?? '' }}</h1>
                        <p>{{ $s['subtitle'] ?? '' }}</p>
                        <div class="d-flex flex-wrap gap-3 mt-4">
                            @if (! empty($s['button_text']))<a href="{{ store_url($s['button_url'] ?? null) }}" wire:navigate class="btn btn-primary btn-lg">{{ $s['button_text'] }}</a>@endif
                            <a href="{{ route('deals') }}" wire:navigate class="btn btn-outline-ink btn-lg" style="background: #fff">View offers</a>
                        </div>
                    </div>
                    <div class="cl-slide-img">@if ($img)<img src="{{ $img }}" alt="">@endif</div>
                </div>
            @endforeach
            @if (count($slides) > 1)
                <div class="cl-dots">
                    @foreach ($slides as $i => $s)<button type="button" :class="{ 'is-active': i === {{ $i }} }" @click="go({{ $i }})" aria-label="Slide {{ $i + 1 }}"></button>@endforeach
                </div>
            @endif
        </div>
    </div>
</section>
