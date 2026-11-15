<?php

use App\Livewire\Concerns\InteractsWithCart;
use App\Models\HomeSection;
use App\Models\NewsletterSubscriber;
use App\Services\Storefront\SectionData;
use Livewire\Volt\Component;

new class extends Component
{
    use InteractsWithCart;

    public string $newsletterEmail = '';

    public bool $subscribed = false;

    public function subscribe(): void
    {
        $this->validate(['newsletterEmail' => 'required|email|max:190'], [], ['newsletterEmail' => 'email']);
        NewsletterSubscriber::firstOrCreate(['email' => strtolower($this->newsletterEmail)]);
        $this->subscribed = true;
        $this->dispatch('store-toast', type: 'success', message: 'Thanks for subscribing!');
    }

    public function with(): array
    {
        $data = app(SectionData::class);
        $sections = HomeSection::forTheme(theme()->active())->where('is_active', true)->get();

        return [
            'blocks' => $sections->map(fn ($s) => ['section' => $s, 'data' => $data->for($s)]),
            'wishlist' => $this->wishlistIds(),
        ];
    }
}; ?>

<div class="sf-home">
    @forelse ($blocks as $block)
        <div wire:key="section-{{ $block['section']->id }}">
            @include(theme_view('sections.'.$block['section']->type), ['section' => $block['section'], 'data' => $block['data']])
        </div>
    @empty
        <div class="container sf-section">
            <div class="sf-empty"><i class="ri-layout-masonry-line"></i><h4>Welcome to {{ store_name() }}</h4><p>The homepage is being set up. Browse the full catalogue meanwhile.</p>
                <a href="{{ route('shop') }}" wire:navigate class="btn btn-primary">Shop now</a></div>
        </div>
    @endforelse
</div>
