<?php

use App\Models\HomeSection;
use App\Support\Settings;
use Database\Seeders\StorefrontSeeder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Storefront themes')] class extends Component
{
    public ?string $customizing = null;

    public array $colors = ['primary' => '', 'accent' => ''];

    public function activate(string $key, Settings $settings): void
    {
        abort_unless(array_key_exists($key, config('themes')), 404);
        $settings->set('store.theme', $key);
        session()->forget('preview_theme');

        if (! HomeSection::where('theme', $key)->exists()) {
            StorefrontSeeder::seedTheme($key);
        }

        $this->dispatch('toast', type: 'success', message: config("themes.$key.name").' is now live on your storefront.');
    }

    public function customize(string $key): void
    {
        $this->customizing = $key;
        $this->colors = [
            'primary' => setting("theme_colors.$key.primary") ?: config("themes.$key.preview.primary"),
            'accent' => setting("theme_colors.$key.accent") ?: config("themes.$key.preview.accent"),
        ];
    }

    public function saveColors(Settings $settings): void
    {
        $this->validate([
            'colors.primary' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'colors.accent' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ], ['regex' => 'Use a 6-digit hex colour.']);

        $settings->set([
            "theme_colors.{$this->customizing}.primary" => strtoupper($this->colors['primary']),
            "theme_colors.{$this->customizing}.accent" => strtoupper($this->colors['accent']),
        ]);
        $this->customizing = null;
        $this->dispatch('toast', type: 'success', message: 'Brand colours saved.');
    }

    public function resetColors(Settings $settings): void
    {
        $settings->set([
            "theme_colors.{$this->customizing}.primary" => null,
            "theme_colors.{$this->customizing}.accent" => null,
        ]);
        $this->customizing = null;
        $this->dispatch('toast', type: 'info', message: 'Theme colours reset to default.');
    }

    public function resetSections(string $key): void
    {
        StorefrontSeeder::seedTheme($key);
        $this->dispatch('toast', type: 'success', message: 'Homepage sections reset for '.config("themes.$key.name").'.');
    }

    public function with(): array
    {
        return [
            'themes' => config('themes'),
            'active' => setting('store.theme', 'noir'),
            'sectionCounts' => HomeSection::selectRaw('theme, COUNT(*) c')->where('is_active', true)->groupBy('theme')->pluck('c', 'theme'),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Storefront themes" subtitle="Pick the look of your public shop. Every theme uses the same products, cart and checkout — only the design changes.">
        <x-slot:actions>
            <a href="{{ route('home') }}" target="_blank" class="btn btn-sm btn-light-primary"><i class="ri-external-link-line me-1"></i>Open live store</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        @foreach ($themes as $key => $t)
            @php($p = $t['preview'])
            @php($primary = setting("theme_colors.$key.primary") ?: $p['primary'])
            @php($accent = setting("theme_colors.$key.accent") ?: $p['accent'])
            <div class="col-md-6 col-xxl-4" wire:key="theme-{{ $key }}">
                <div class="card mb-0 h-100 mw-theme-card {{ $active === $key ? 'is-active' : '' }}">
                    <div class="mw-theme-preview" style="background: {{ $p['bg'] }}">
                        <div class="tp-nav" style="background: {{ $p['surface'] }}; border: 1px solid rgba(127,127,127,.15)"></div>
                        <div class="tp-title" style="background: {{ $p['text'] }}"></div>
                        <div class="tp-line" style="background: {{ $p['text'] }}"></div>
                        <div class="tp-btn" style="background: {{ $primary }}"></div>
                        <div class="tp-phone" style="background: {{ $accent }}; box-shadow: 0 10px 30px {{ $accent }}55"></div>
                        <div class="tp-cards">
                            @for ($i = 0; $i < 4; $i++)
                                <div class="tp-card" style="background: {{ $p['surface'] }}; border: 1px solid rgba(127,127,127,.15)"></div>
                            @endfor
                        </div>
                        @if ($active === $key)
                            <span class="badge bg-success position-absolute top-0 end-0 m-3"><i class="ri-checkbox-circle-fill me-1"></i>Live</span>
                        @endif
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <h5 class="mb-0">{{ $t['name'] }}</h5>
                            @if ($t['dark'])<span class="badge bg-dark">Dark</span>@endif
                        </div>
                        <p class="text-primary fs-13 fw-medium mb-2">{{ $t['tagline'] }}</p>
                        <p class="text-muted fs-13 mb-3">{{ $t['description'] }}</p>
                        <div class="d-flex align-items-center gap-2 fs-12 text-muted">
                            <span class="mw-swatch" style="background: {{ $primary }}"></span>
                            <span class="mw-swatch" style="background: {{ $accent }}"></span>
                            <span class="mw-swatch" style="background: {{ $p['bg'] }}"></span>
                            <span class="ms-1">{{ $sectionCounts[$key] ?? 0 }} homepage sections</span>
                        </div>
                    </div>
                    <div class="card-footer d-flex flex-wrap gap-2">
                        @if ($active === $key)
                            <button class="btn btn-sm btn-success" disabled><i class="ri-check-line me-1"></i>Active</button>
                        @else
                            <button class="btn btn-sm btn-primary" x-data @click="confirmAction('Switch your live storefront to {{ $t['name'] }}?', () => $wire.activate('{{ $key }}'), { icon: 'question', title: 'Activate theme', confirmText: 'Activate' })"><i class="ri-rocket-line me-1"></i>Activate</button>
                        @endif
                        <a href="{{ route('home', ['preview_theme' => $key]) }}" target="_blank" class="btn btn-sm btn-light"><i class="ri-eye-line me-1"></i>Preview</a>
                        <button class="btn btn-sm btn-light" wire:click="customize('{{ $key }}')"><i class="ri-palette-line me-1"></i>Colours</button>
                        <div class="dropdown ms-auto">
                            <button class="btn btn-sm btn-light icon-btn-sm" data-bs-toggle="dropdown"><i class="ri-more-2-fill"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <a class="dropdown-item" href="{{ route('admin.storefront.homepage', ['theme' => $key]) }}" wire:navigate><i class="ri-layout-masonry-line me-2"></i>Edit homepage sections</a>
                                <button class="dropdown-item text-danger" x-data @click="confirmAction('Replace this theme’s homepage sections with the defaults?', () => $wire.resetSections('{{ $key }}'), { confirmText: 'Reset' })"><i class="ri-restart-line me-2"></i>Reset homepage to default</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-admin.modal :show="(bool) $customizing" close="$set('customizing', null)" :title="'Brand colours — '.($customizing ? config('themes.'.$customizing.'.name') : '')" subtitle="Override the theme’s accent colours to match your brand.">
        <form wire:submit="saveColors" id="tc-form" class="row g-3">
            @foreach (['primary' => 'Primary (buttons, links)', 'accent' => 'Accent (highlights, badges)'] as $k => $label)
                <div class="col-md-6">
                    <label class="form-label">{{ $label }}</label>
                    <div class="input-group">
                        <input type="color" class="form-control form-control-color" wire:model.live="colors.{{ $k }}">
                        <input type="text" class="form-control font-monospace @error('colors.'.$k) is-invalid @enderror" wire:model.live.debounce.400ms="colors.{{ $k }}">
                    </div>
                    @error('colors.'.$k)<div class="text-danger fs-12">{{ $message }}</div>@enderror
                </div>
            @endforeach
            <div class="col-12">
                <div class="rounded-3 p-3 d-flex gap-2 align-items-center" style="background: {{ $customizing ? config('themes.'.$customizing.'.preview.bg') : '#fff' }}">
                    <span class="btn btn-sm text-white" style="background: {{ $colors['primary'] }}">Add to cart</span>
                    <span class="badge" style="background: {{ $colors['accent'] }}">-25%</span>
                </div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light me-auto" wire:click="resetColors">Reset to theme default</button>
            <button type="button" class="btn btn-light" wire:click="$set('customizing', null)">Cancel</button>
            <button type="submit" form="tc-form" class="btn btn-primary">Save colours</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
