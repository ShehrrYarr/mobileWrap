<?php

use App\Models\Category;
use App\Models\Deal;
use App\Models\HomeSection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Homepage builder')] class extends Component
{
    use WithFileUploads;

    #[Url]
    public string $theme = '';

    public ?int $editingId = null;

    public array $editing = ['title' => '', 'subtitle' => '', 'settings' => []];

    public string $editingType = '';

    /** uploads for repeater images, keyed like "slides.0" */
    public array $images = [];

    public bool $showAdd = false;

    public function mount(): void
    {
        if (! array_key_exists($this->theme, config('themes'))) {
            $this->theme = setting('store.theme', 'noir');
        }
    }

    public function move(int $id, int $dir): void
    {
        $sections = HomeSection::forTheme($this->theme)->get()->values();
        $i = $sections->search(fn ($s) => $s->id === $id);
        $j = $i + $dir;
        if ($i === false || ! isset($sections[$j])) {
            return;
        }
        [$a, $b] = [$sections[$i], $sections[$j]];
        $sections[$i] = $b;
        $sections[$j] = $a;
        foreach ($sections as $k => $s) {
            $s->update(['sort_order' => $k]);
        }
    }

    public function toggle(int $id): void
    {
        $s = HomeSection::findOrFail($id);
        $s->update(['is_active' => ! $s->is_active]);
    }

    public function add(string $type): void
    {
        abort_unless(array_key_exists($type, HomeSection::TYPES), 404);
        $defaults = [
            'hero' => ['slides' => [['badge' => '', 'title' => 'New headline', 'subtitle' => '', 'button_text' => 'Shop now', 'button_url' => '/shop', 'image' => null]]],
            'features' => ['items' => [['icon' => 'ri-truck-line', 'title' => 'Fast delivery', 'text' => 'Nationwide']]],
            'product_rail' => ['source' => 'featured', 'limit' => 8],
            'promo_banner' => ['banners' => [['title' => 'Promo', 'subtitle' => '', 'button_text' => 'Shop', 'button_url' => '/shop', 'image' => null]]],
            'testimonials' => ['items' => [['name' => 'Customer', 'city' => 'Lahore', 'rating' => 5, 'text' => 'Great service!']]],
            'newsletter' => ['button_text' => 'Subscribe'],
        ][$type] ?? ['limit' => 8];

        $section = HomeSection::create([
            'theme' => $this->theme, 'type' => $type, 'title' => HomeSection::TYPES[$type]['label'],
            'settings' => $defaults, 'sort_order' => HomeSection::where('theme', $this->theme)->max('sort_order') + 1,
        ]);
        $this->showAdd = false;
        $this->edit($section->id);
    }

    public function duplicate(int $id): void
    {
        $s = HomeSection::findOrFail($id)->replicate();
        $s->title = trim(($s->title ?? '').' (copy)');
        $s->sort_order = HomeSection::where('theme', $this->theme)->max('sort_order') + 1;
        $s->save();
    }

    public function delete(int $id): void
    {
        HomeSection::where('theme', $this->theme)->findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Section removed.');
    }

    public function edit(int $id): void
    {
        $s = HomeSection::findOrFail($id);
        $this->editingId = $s->id;
        $this->editingType = $s->type;
        $this->editing = ['title' => (string) $s->title, 'subtitle' => (string) $s->subtitle, 'settings' => $s->settings ?? []];
        if ($s->type === 'categories') {
            $this->editing['settings']['category_ids'] = array_map('strval', $this->editing['settings']['category_ids'] ?? []);
        }
        $this->images = [];
        $this->resetErrorBag();
    }

    public function addRow(string $list): void
    {
        $blank = [
            'slides' => ['badge' => '', 'title' => '', 'subtitle' => '', 'button_text' => 'Shop now', 'button_url' => '/shop', 'image' => null],
            'banners' => ['title' => '', 'subtitle' => '', 'button_text' => 'Shop', 'button_url' => '/shop', 'image' => null],
            'items' => $this->editingType === 'testimonials' ? ['name' => '', 'city' => '', 'rating' => 5, 'text' => ''] : ['icon' => 'ri-star-line', 'title' => '', 'text' => ''],
        ][$list];
        $this->editing['settings'][$list][] = $blank;
    }

    public function removeRow(string $list, int $i): void
    {
        array_splice($this->editing['settings'][$list], $i, 1);
    }

    public function updatedImages($value, $key): void
    {
        $this->validate(["images.$key" => 'image|max:4096']);
        [$list, $i] = explode('.', $key);
        $this->editing['settings'][$list][(int) $i]['image'] = $this->images[$list][$i]->store('storefront', 'uploads');
        unset($this->images[$list][$i]);
    }

    public function save(): void
    {
        $this->validate([
            'editing.title' => 'nullable|string|max:150',
            'editing.subtitle' => 'nullable|string|max:255',
            'editing.settings.limit' => 'nullable|integer|min:1|max:24',
            'editing.settings.slides.*.title' => 'required|string|max:150',
            'editing.settings.banners.*.title' => 'required|string|max:120',
            'editing.settings.items.*.title' => $this->editingType === 'features' ? 'required|string|max:80' : 'nullable',
            'editing.settings.items.*.text' => 'nullable|string|max:400',
        ], [], ['editing.settings.slides.*.title' => 'slide headline', 'editing.settings.banners.*.title' => 'banner title']);

        HomeSection::findOrFail($this->editingId)->update([
            'title' => $this->editing['title'] ?: null,
            'subtitle' => $this->editing['subtitle'] ?: null,
            'settings' => $this->editing['settings'],
        ]);
        $this->editingId = null;
        $this->dispatch('toast', type: 'success', message: 'Section saved.');
    }

    public function with(): array
    {
        return [
            'themes' => config('themes'),
            'sections' => HomeSection::forTheme($this->theme)->get(),
            'categories' => Category::where('is_active', true)->orderBy('name')->get(),
            'flashDeals' => Deal::ofType('flash_sale')->latest('id')->get(),
            'isLive' => setting('store.theme') === $this->theme,
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Homepage builder" subtitle="Turn sections on/off, reorder them and edit their content. Each theme keeps its own homepage.">
        <x-slot:actions>
            <select class="form-select form-select-sm" style="width: 200px" wire:model.live="theme">
                @foreach ($themes as $k => $t)<option value="{{ $k }}">{{ $t['name'] }}{{ setting('store.theme') === $k ? ' (live)' : '' }}</option>@endforeach
            </select>
            <a href="{{ route('home', $isLive ? [] : ['preview_theme' => $theme]) }}" target="_blank" class="btn btn-sm btn-light-primary"><i class="ri-eye-line me-1"></i>{{ $isLive ? 'View homepage' : 'Preview' }}</a>
            <button class="btn btn-sm btn-primary" wire:click="$set('showAdd', true)"><i class="ri-add-line me-1"></i>Add section</button>
        </x-slot:actions>
    </x-admin.page-header>

    @unless ($isLive)
        <div class="alert alert-info d-flex align-items-center gap-2"><i class="ri-information-line fs-18"></i> You’re editing <strong>{{ $themes[$theme]['name'] }}</strong>, which isn’t the live theme. Changes show up when it’s activated (or in Preview).</div>
    @endunless

    <div class="d-flex flex-column gap-2">
        @forelse ($sections as $i => $s)
            <div class="mw-section-row p-3 d-flex align-items-center gap-3 {{ $s->is_active ? '' : 'is-off' }}" wire:key="sec-{{ $s->id }}">
                <div class="d-flex flex-column">
                    <button class="btn btn-sm btn-light icon-btn-sm mb-1" wire:click="move({{ $s->id }}, -1)" @disabled($i === 0) title="Move up"><i class="ri-arrow-up-s-line"></i></button>
                    <button class="btn btn-sm btn-light icon-btn-sm" wire:click="move({{ $s->id }}, 1)" @disabled($i === $sections->count() - 1) title="Move down"><i class="ri-arrow-down-s-line"></i></button>
                </div>
                <span class="mw-stat-icon bg-primary-subtle text-primary"><i class="{{ \App\Models\HomeSection::TYPES[$s->type]['icon'] ?? 'ri-layout-line' }}"></i></span>
                <div class="flex-grow-1 min-w-0">
                    <div class="fs-12 text-muted text-uppercase fw-semibold">{{ $s->typeLabel() }}</div>
                    <div class="fw-semibold text-truncate">{{ $s->title ?: ($s->type === 'hero' ? ($s->setting('slides.0.title') ?? 'Hero') : '—') }}</div>
                    @if ($s->subtitle)<div class="fs-12 text-muted text-truncate">{{ $s->subtitle }}</div>@endif
                </div>
                <div class="form-check form-switch mb-0" title="Show on homepage"><input class="form-check-input" type="checkbox" @checked($s->is_active) wire:click="toggle({{ $s->id }})"></div>
                <button class="btn btn-sm btn-light-primary" wire:click="edit({{ $s->id }})"><i class="ri-pencil-line me-1"></i>Edit</button>
                <div class="dropdown">
                    <button class="btn btn-sm btn-light icon-btn-sm" data-bs-toggle="dropdown"><i class="ri-more-2-fill"></i></button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <button class="dropdown-item" wire:click="duplicate({{ $s->id }})"><i class="ri-file-copy-line me-2"></i>Duplicate</button>
                        <button class="dropdown-item text-danger" x-data @click="confirmAction('Remove this section from the homepage?', () => $wire.delete({{ $s->id }}), { confirmText: 'Remove' })"><i class="ri-delete-bin-line me-2"></i>Remove</button>
                    </div>
                </div>
            </div>
        @empty
            <div class="card"><x-admin.empty icon="ri-layout-masonry-line" title="No sections" text="Add sections to build this theme’s homepage." /></div>
        @endforelse
    </div>

    {{-- Add section --}}
    <x-admin.modal :show="$showAdd" close="$set('showAdd', false)" title="Add a section" size="lg">
        <div class="row g-3">
            @foreach (\App\Models\HomeSection::TYPES as $type => $meta)
                <div class="col-sm-6 col-lg-4">
                    <button type="button" class="btn btn-light w-100 text-start p-3 h-100" wire:click="add('{{ $type }}')">
                        <i class="{{ $meta['icon'] }} fs-3 text-primary d-block mb-1"></i>
                        <span class="fw-semibold">{{ $meta['label'] }}</span>
                    </button>
                </div>
            @endforeach
        </div>
    </x-admin.modal>

    {{-- Edit section --}}
    <x-admin.modal :show="(bool) $editingId" close="$set('editingId', null)" :title="'Edit '.strtolower(\App\Models\HomeSection::TYPES[$editingType]['label'] ?? 'section')" size="lg">
        <form wire:submit="save" id="sec-form" class="row g-3">
            @if ($editingType !== 'hero')
                <div class="col-md-6"><label class="form-label">Heading</label><input class="form-control" wire:model="editing.title"></div>
                <div class="col-md-6"><label class="form-label">Sub-heading</label><input class="form-control" wire:model="editing.subtitle"></div>
            @endif

            @if ($editingType === 'hero' || $editingType === 'promo_banner')
                @php($list = $editingType === 'hero' ? 'slides' : 'banners')
                @foreach ($editing['settings'][$list] ?? [] as $i => $row)
                    <div class="col-12" wire:key="{{ $list }}-{{ $i }}">
                        <div class="border rounded-3 p-3">
                            <div class="d-flex align-items-center mb-2">
                                <h6 class="mb-0 flex-grow-1 fs-13">{{ $editingType === 'hero' ? 'Slide' : 'Banner' }} {{ $i + 1 }}</h6>
                                <button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" wire:click="removeRow('{{ $list }}', {{ $i }})"><i class="ri-close-line"></i></button>
                            </div>
                            <div class="row g-2">
                                @if ($editingType === 'hero')
                                    <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Badge (small label)" wire:model="editing.settings.{{ $list }}.{{ $i }}.badge"></div>
                                @endif
                                <div class="{{ $editingType === 'hero' ? 'col-md-8' : 'col-12' }}"><input class="form-control form-control-sm @error('editing.settings.'.$list.'.'.$i.'.title') is-invalid @enderror" placeholder="Headline *" wire:model="editing.settings.{{ $list }}.{{ $i }}.title"></div>
                                <div class="col-12"><input class="form-control form-control-sm" placeholder="Supporting text" wire:model="editing.settings.{{ $list }}.{{ $i }}.subtitle"></div>
                                <div class="col-md-5"><input class="form-control form-control-sm" placeholder="Button text" wire:model="editing.settings.{{ $list }}.{{ $i }}.button_text"></div>
                                <div class="col-md-7"><input class="form-control form-control-sm" placeholder="Button link, e.g. /shop?category=chargers" wire:model="editing.settings.{{ $list }}.{{ $i }}.button_url"></div>
                                <div class="col-12 d-flex align-items-center gap-2">
                                    @if (! empty($row['image']))
                                        <img src="{{ upload_url($row['image']) }}" class="mw-thumb" alt="">
                                        <button type="button" class="btn btn-sm btn-link text-danger p-0" wire:click="$set('editing.settings.{{ $list }}.{{ $i }}.image', null)">Remove image</button>
                                    @else
                                        <span class="fs-12 text-muted">No image — the theme uses featured product shots.</span>
                                    @endif
                                    <input type="file" class="form-control form-control-sm ms-auto" style="max-width: 240px" accept="image/*" wire:model="images.{{ $list }}.{{ $i }}">
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
                <div class="col-12"><button type="button" class="btn btn-sm btn-light" wire:click="addRow('{{ $list }}')"><i class="ri-add-line me-1"></i>Add {{ $editingType === 'hero' ? 'slide' : 'banner' }}</button></div>
            @endif

            @if ($editingType === 'features' || $editingType === 'testimonials')
                @foreach ($editing['settings']['items'] ?? [] as $i => $row)
                    <div class="col-12" wire:key="items-{{ $i }}">
                        <div class="border rounded-3 p-2 d-flex gap-2 align-items-start">
                            <div class="row g-2 flex-grow-1">
                                @if ($editingType === 'features')
                                    <div class="col-md-3"><div class="input-group input-group-sm"><span class="input-group-text"><i class="{{ $row['icon'] ?? '' }}"></i></span><input class="form-control" placeholder="ri-truck-line" wire:model.live.debounce.500ms="editing.settings.items.{{ $i }}.icon"></div></div>
                                    <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Title" wire:model="editing.settings.items.{{ $i }}.title"></div>
                                    <div class="col-md-5"><input class="form-control form-control-sm" placeholder="Text" wire:model="editing.settings.items.{{ $i }}.text"></div>
                                @else
                                    <div class="col-md-4"><input class="form-control form-control-sm" placeholder="Name" wire:model="editing.settings.items.{{ $i }}.name"></div>
                                    <div class="col-md-4"><input class="form-control form-control-sm" placeholder="City" wire:model="editing.settings.items.{{ $i }}.city"></div>
                                    <div class="col-md-4"><select class="form-select form-select-sm" wire:model="editing.settings.items.{{ $i }}.rating">@for ($r = 5; $r >= 1; $r--)<option value="{{ $r }}">{{ $r }} ★</option>@endfor</select></div>
                                    <div class="col-12"><textarea class="form-control form-control-sm" rows="2" placeholder="Review" wire:model="editing.settings.items.{{ $i }}.text"></textarea></div>
                                @endif
                            </div>
                            <button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" wire:click="removeRow('items', {{ $i }})"><i class="ri-close-line"></i></button>
                        </div>
                    </div>
                @endforeach
                <div class="col-12"><button type="button" class="btn btn-sm btn-light" wire:click="addRow('items')"><i class="ri-add-line me-1"></i>Add {{ $editingType === 'features' ? 'highlight' : 'review' }}</button></div>
            @endif

            @if ($editingType === 'product_rail')
                <div class="col-md-5"><label class="form-label">Show</label>
                    <select class="form-select" wire:model.live="editing.settings.source">
                        <option value="featured">Featured products</option><option value="new">Newest products</option><option value="bestsellers">Best sellers</option><option value="sale">On sale</option><option value="category">From a category</option>
                    </select>
                </div>
                @if (($editing['settings']['source'] ?? '') === 'category')
                    <div class="col-md-5"><label class="form-label">Category</label><select class="form-select" wire:model="editing.settings.category_id"><option value="">Choose…</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select></div>
                @endif
            @endif

            @if ($editingType === 'categories')
                <div class="col-12">
                    <label class="form-label">Categories to show <span class="text-muted fs-12">(none = featured categories)</span></label>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach ($categories as $c)
                            <input type="checkbox" class="btn-check" id="hc-{{ $c->id }}" value="{{ $c->id }}" wire:model="editing.settings.category_ids">
                            <label class="btn btn-sm btn-outline-primary" for="hc-{{ $c->id }}">{{ $c->name }}</label>
                        @endforeach
                    </div>
                </div>
            @endif

            @if ($editingType === 'flash_sale')
                <div class="col-md-6"><label class="form-label">Flash sale</label>
                    <select class="form-select" wire:model="editing.settings.deal_id"><option value="">Automatic (first live flash sale)</option>@foreach ($flashDeals as $d)<option value="{{ $d->id }}">{{ $d->title }}{{ $d->isLive() ? ' · live' : '' }}</option>@endforeach</select>
                    <div class="form-text fs-12">The section hides itself when no flash sale is running.</div>
                </div>
            @endif

            @if (in_array($editingType, ['product_rail', 'categories', 'flash_sale', 'shop_by_device', 'bundles']))
                <div class="col-md-2"><label class="form-label">Limit</label><input type="number" min="1" max="24" class="form-control" wire:model="editing.settings.limit"></div>
            @endif

            @if ($editingType === 'newsletter')
                <div class="col-md-6"><label class="form-label">Button text</label><input class="form-control" wire:model="editing.settings.button_text"></div>
            @endif

            @if ($editingType === 'brands')
                <div class="col-12"><p class="text-muted fs-13 mb-0">Shows all active brands. Manage them under Products → Brands.</p></div>
            @endif
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('editingId', null)">Cancel</button>
            <button type="submit" form="sec-form" class="btn btn-primary">Save section</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
