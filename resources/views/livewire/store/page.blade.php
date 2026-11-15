<?php

use Livewire\Volt\Component;

new class extends Component
{
    public string $slug;

    public array $page = [];

    public function mount(string $slug): void
    {
        $page = data_get(setting('pages', []), $slug);
        abort_unless(is_array($page), 404);
        $this->slug = $slug;
        $this->page = $page;
    }

    public function rendering($view): void
    {
        $view->title($this->page['title'] ?? 'Page');
    }
}; ?>

<section class="sf-section">
    <div class="container" style="max-width: 820px">
        <nav class="sf-breadcrumb pt-0"><a href="{{ route('home') }}" wire:navigate>Home</a><i class="ri-arrow-right-s-line"></i><span>{{ $page['title'] ?? '' }}</span></nav>
        <h1 class="sf-title mb-4">{{ $page['title'] ?? '' }}</h1>
        <div class="sf-prose" style="font-size: 17px">{{ $page['content'] ?? '' }}</div>
        @if ($slug === 'about')
            <div class="sf-card-panel mt-5 d-flex flex-wrap gap-4 justify-content-between">
                <div><div class="sf-muted" style="font-size: 13px">Visit</div><b>{{ setting('store.address') }}, {{ setting('store.city') }}</b></div>
                <div><div class="sf-muted" style="font-size: 13px">Call</div><b>{{ setting('store.phone') }}</b></div>
                <div><div class="sf-muted" style="font-size: 13px">Hours</div><b>{{ setting('store.hours') }}</b></div>
            </div>
        @endif
    </div>
</section>
