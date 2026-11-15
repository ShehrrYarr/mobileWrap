<?php

use App\Models\Category;
use App\Models\Deal;
use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Deals & bundles')] class extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    /** [{product_id, quantity}] */
    public array $items = [];

    public string $productSearch = '';

    public $banner = null;

    public function create(string $type = 'flash_sale'): void
    {
        $this->editingId = null;
        $this->banner = null;
        $this->form = [
            'title' => '', 'type' => $type, 'discount_type' => 'percent', 'discount_value' => 20, 'bundle_price' => '',
            'category_id' => '', 'description' => '', 'starts_at' => now()->format('Y-m-d\TH:i'),
            'ends_at' => now()->addDays(3)->format('Y-m-d\TH:i'), 'show_on_home' => true, 'is_active' => true, 'banner_image' => null,
        ];
        $this->items = [];
        $this->productSearch = '';
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $deal = Deal::with('items')->findOrFail($id);
        $this->create($deal->type);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $deal->{$k} ?? '';
        }
        $this->form['starts_at'] = $deal->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->form['ends_at'] = $deal->ends_at?->format('Y-m-d\TH:i') ?? '';
        $this->form['discount_value'] = (float) $deal->discount_value;
        $this->form['bundle_price'] = $deal->bundle_price !== null ? (float) $deal->bundle_price : '';
        $this->form['category_id'] = (string) ($deal->category_id ?? '');
        $this->form['description'] = (string) $deal->description;
        $this->items = $deal->items->map(fn ($i) => ['product_id' => $i->product_id, 'quantity' => $i->quantity])->all();
    }

    public function addItem(int $productId): void
    {
        if (! collect($this->items)->contains('product_id', $productId)) {
            $this->items[] = ['product_id' => $productId, 'quantity' => 1];
        }
        $this->productSearch = '';
    }

    public function removeItem(int $i): void
    {
        array_splice($this->items, $i, 1);
    }

    public function save(): void
    {
        $type = $this->form['type'];
        $this->validate([
            'form.title' => 'required|string|max:120',
            'form.type' => 'required|in:flash_sale,bundle,category_discount',
            'form.discount_type' => 'required|in:percent,fixed',
            'form.discount_value' => $type === 'bundle' ? 'nullable' : 'required|numeric|min:1',
            'form.bundle_price' => $type === 'bundle' ? 'required|numeric|min:1' : 'nullable',
            'form.category_id' => $type === 'category_discount' ? 'required|exists:categories,id' : 'nullable',
            'form.starts_at' => 'nullable|date',
            'form.ends_at' => 'nullable|date|after:form.starts_at',
            'items' => $type === 'category_discount' ? 'array' : 'required|array|min:'.($type === 'bundle' ? 2 : 1),
            'banner' => 'nullable|image|max:3072',
        ], ['items.required' => 'Pick at least one product.', 'items.min' => 'A bundle needs at least two products.'], ['form.title' => 'title', 'form.category_id' => 'category', 'form.bundle_price' => 'bundle price']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $data['discount_value'] ??= 0;
        if ($this->banner) {
            $data['banner_image'] = $this->banner->store('deals', 'uploads');
        }
        $deal = Deal::updateOrCreate(['id' => $this->editingId], $data);

        $deal->items()->delete();
        if ($type !== 'category_discount') {
            foreach ($this->items as $item) {
                $product = Product::find($item['product_id']);
                $deal->items()->create([
                    'product_id' => $item['product_id'],
                    'product_variant_id' => $type === 'bundle' ? $product?->defaultVariant()?->id : null,
                    'quantity' => max(1, (int) $item['quantity']),
                ]);
            }
        }

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Deal saved.');
    }

    public function toggle(int $id): void
    {
        $d = Deal::findOrFail($id);
        $d->update(['is_active' => ! $d->is_active]);
    }

    public function delete(int $id): void
    {
        Deal::findOrFail($id)->delete();
        $this->dispatch('toast', type: 'success', message: 'Deal deleted.');
    }

    public function with(): array
    {
        $picked = collect($this->items)->pluck('product_id');

        return [
            'deals' => Deal::with(['items.product', 'category'])->latest('id')->get(),
            'categories' => Category::orderBy('name')->get(),
            'results' => strlen($this->productSearch) >= 2
                ? Product::online()->search($this->productSearch)->whereNotIn('id', $picked)->limit(6)->get()
                : collect(),
            'pickedProducts' => Product::with('activeVariants', 'colors.images')->whereIn('id', $picked)->get()->keyBy('id'),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Deals & bundles" subtitle="Flash sales and category discounts change storefront prices automatically while they’re live. Bundles are sold as combos.">
        <x-slot:actions>
            <div class="dropdown">
                <button class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"><i class="ri-add-line me-1"></i>New deal</button>
                <div class="dropdown-menu dropdown-menu-end">
                    @foreach (\App\Models\Deal::TYPES as $k => $label)
                        <button class="dropdown-item" wire:click="create('{{ $k }}')">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        @forelse ($deals as $deal)
            @php($live = $deal->isLive())
            <div class="col-md-6 col-xl-4" wire:key="deal-{{ $deal->id }}">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-2 mb-2">
                            <span class="badge {{ ['flash_sale' => 'bg-danger-subtle text-danger', 'bundle' => 'bg-warning-subtle text-warning', 'category_discount' => 'bg-info-subtle text-info'][$deal->type] }}">{{ \App\Models\Deal::TYPES[$deal->type] }}</span>
                            @if ($live)<span class="badge bg-success"><i class="ri-record-circle-fill"></i> Live</span>@elseif ($deal->ends_at?->isPast())<span class="badge bg-light text-muted">Ended</span>@else<span class="badge bg-light text-muted">Scheduled / off</span>@endif
                            <div class="ms-auto form-check form-switch mb-0"><input type="checkbox" class="form-check-input" @checked($deal->is_active) wire:click="toggle({{ $deal->id }})"></div>
                        </div>
                        <h5 class="mb-1">{{ $deal->title }}</h5>
                        <p class="fs-13 text-muted mb-2">{{ $deal->description }}</p>
                        <div class="fs-13 mb-2">
                            @if ($deal->type === 'bundle')
                                Bundle price <strong>{{ money($deal->bundle_price) }}</strong> <span class="text-muted text-decoration-line-through">{{ money($deal->bundleRegularTotal()) }}</span>
                            @else
                                <strong>{{ $deal->discountLabel() }}</strong> on {{ $deal->type === 'category_discount' ? $deal->category?->name : $deal->items->count().' products' }}
                            @endif
                        </div>
                        <div class="d-flex flex-wrap gap-1 mb-2">
                            @foreach ($deal->items->take(6) as $item)
                                <span class="badge bg-light text-body fw-normal">{{ $item->quantity > 1 ? $item->quantity.'× ' : '' }}{{ $item->product?->name }}</span>
                            @endforeach
                        </div>
                        <div class="fs-12 text-muted"><i class="ri-calendar-line"></i> {{ $deal->starts_at?->format('d M, h:i a') ?? 'Now' }} → {{ $deal->ends_at?->format('d M, h:i a') ?? 'No end' }}</div>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button class="btn btn-sm btn-light-primary" wire:click="edit({{ $deal->id }})"><i class="ri-pencil-line me-1"></i>Edit</button>
                        <button class="btn btn-sm btn-light-danger ms-auto" x-data @click="confirmAction('Delete “{{ addslashes($deal->title) }}”?', () => $wire.delete({{ $deal->id }}))"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card"><x-admin.empty icon="ri-flashlight-line" title="No deals yet" text="Create a flash sale, a category discount or a combo bundle." /></div></div>
        @endforelse
    </div>

    <x-admin.modal :show="$showForm" :title="($editingId ? 'Edit ' : 'New ').strtolower(\App\Models\Deal::TYPES[$form['type'] ?? 'flash_sale'] ?? 'deal')" size="lg">
        <form wire:submit="save" id="deal-form" class="row g-3">
            <div class="col-md-8"><label class="form-label">Title *</label><input class="form-control @error('form.title') is-invalid @enderror" wire:model="form.title" placeholder="e.g. Weekend Flash Sale">@error('form.title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" wire:model.live="form.type">@foreach (\App\Models\Deal::TYPES as $k => $l)<option value="{{ $k }}">{{ $l }}</option>@endforeach</select></div>

            @if (($form['type'] ?? '') === 'bundle')
                <div class="col-md-6"><label class="form-label">Bundle price (Rs.) *</label><input type="number" class="form-control @error('form.bundle_price') is-invalid @enderror" wire:model="form.bundle_price">@error('form.bundle_price')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @else
                <div class="col-md-3"><label class="form-label">Discount</label><select class="form-select" wire:model="form.discount_type"><option value="percent">%</option><option value="fixed">Rs.</option></select></div>
                <div class="col-md-3"><label class="form-label">Value *</label><input type="number" class="form-control @error('form.discount_value') is-invalid @enderror" wire:model="form.discount_value"></div>
            @endif

            @if (($form['type'] ?? '') === 'category_discount')
                <div class="col-md-6"><label class="form-label">Category *</label><select class="form-select @error('form.category_id') is-invalid @enderror" wire:model="form.category_id"><option value="">Choose…</option>@foreach ($categories as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach</select>@error('form.category_id')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            @else
                <div class="col-12">
                    <label class="form-label">Products {{ ($form['type'] ?? '') === 'bundle' ? 'in this bundle' : 'on sale' }}</label>
                    <div class="position-relative mb-2">
                        <input type="search" class="form-control" placeholder="Type to search products…" wire:model.live.debounce.250ms="productSearch">
                        @if ($results->isNotEmpty())
                            <div class="list-group position-absolute w-100 shadow" style="z-index: 5">
                                @foreach ($results as $r)
                                    <button type="button" class="list-group-item list-group-item-action d-flex align-items-center gap-2" wire:click="addItem({{ $r->id }})">
                                        <img src="{{ $r->imageUrl() }}" class="mw-thumb" style="width:32px;height:32px" alt=""> {{ $r->name }}
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    </div>
                    @error('items')<div class="text-danger fs-12 mb-1">{{ $message }}</div>@enderror
                    <div class="d-flex flex-column gap-2">
                        @foreach ($items as $i => $item)
                            @php($p = $pickedProducts[$item['product_id']] ?? null)
                            <div class="d-flex align-items-center gap-2 border rounded-3 p-2" wire:key="di-{{ $item['product_id'] }}">
                                <img src="{{ $p?->imageUrl() }}" class="mw-thumb" style="width:36px;height:36px" alt="">
                                <span class="flex-grow-1 fs-13">{{ $p?->name }} <span class="text-muted">· {{ money($p?->defaultVariant()?->retail_price) }}</span></span>
                                @if (($form['type'] ?? '') === 'bundle')
                                    <input type="number" min="1" class="form-control form-control-sm" style="width: 70px" wire:model="items.{{ $i }}.quantity">
                                @endif
                                <button type="button" class="btn btn-sm btn-light-danger icon-btn-sm" wire:click="removeItem({{ $i }})"><i class="ri-close-line"></i></button>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

            <div class="col-md-6"><label class="form-label">Starts</label><input type="datetime-local" class="form-control" wire:model="form.starts_at"></div>
            <div class="col-md-6"><label class="form-label">Ends</label><input type="datetime-local" class="form-control @error('form.ends_at') is-invalid @enderror" wire:model="form.ends_at">@error('form.ends_at')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label">Description</label><input class="form-control" wire:model="form.description"></div>
            <div class="col-12"><label class="form-label">Banner image (optional)</label><input type="file" class="form-control" wire:model="banner" accept="image/*"></div>
            <div class="col-12 d-flex gap-4">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="d-act" wire:model="form.is_active"><label class="form-check-label" for="d-act">Active</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="d-home" wire:model="form.show_on_home"><label class="form-check-label" for="d-home">Show on homepage</label></div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="deal-form" class="btn btn-primary">Save deal</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
