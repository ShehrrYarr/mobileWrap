<?php

use App\Models\Brand;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Brands')] class extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = ['name' => '', 'slug' => '', 'description' => '', 'sort_order' => 0, 'is_active' => true, 'logo' => null];

    public $logo = null;

    public function create(): void
    {
        $this->reset('editingId', 'logo');
        $this->form = ['name' => '', 'slug' => '', 'description' => '', 'sort_order' => 0, 'is_active' => true, 'logo' => null];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $brand = Brand::findOrFail($id);
        $this->editingId = $id;
        $this->form = array_merge($this->form, $brand->only(array_keys($this->form)));
        $this->form['description'] = (string) $brand->description;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:120',
            'form.slug' => ['nullable', 'alpha_dash', Rule::unique('brands', 'slug')->ignore($this->editingId)],
            'logo' => 'nullable|image|max:2048',
        ], [], ['form.name' => 'name']);

        $data = $this->form;
        $data['slug'] = $data['slug'] ?: null;
        if ($this->logo) {
            $data['logo'] = $this->logo->store('brands', 'uploads');
        }
        Brand::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Brand saved.');
    }

    public function delete(int $id): void
    {
        $brand = Brand::findOrFail($id);
        $brand->delete(); // products keep working (brand_id nulls out)
        $this->dispatch('toast', type: 'success', message: 'Brand deleted.');
    }

    public function with(): array
    {
        return ['brands' => Brand::withCount('products')->orderBy('sort_order')->orderBy('name')->get()];
    }
}; ?>

<div>
    <x-admin.page-header title="Brands" :crumbs="['Products' => route('admin.products.index')]">
        <x-slot:actions>
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>New brand</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-3">
        @foreach ($brands as $brand)
            <div class="col-sm-6 col-lg-4 col-xxl-3" wire:key="b-{{ $brand->id }}">
                <div class="card mb-0 h-100">
                    <div class="card-body d-flex align-items-center gap-3">
                        @if ($brand->logo)
                            <img src="{{ upload_url($brand->logo) }}" class="mw-thumb" alt="">
                        @else
                            <span class="avatar avatar-item bg-primary-subtle text-primary fw-bold rounded-3">{{ Str::upper(Str::substr($brand->name, 0, 2)) }}</span>
                        @endif
                        <div class="flex-grow-1 min-w-0">
                            <h6 class="mb-0 text-truncate">{{ $brand->name }} @unless ($brand->is_active)<span class="badge bg-light text-muted ms-1">Hidden</span>@endunless</h6>
                            <span class="fs-12 text-muted">{{ $brand->products_count }} products</span>
                        </div>
                        <div class="dropdown">
                            <button class="btn btn-sm btn-light icon-btn-sm" data-bs-toggle="dropdown"><i class="ri-more-2-fill"></i></button>
                            <div class="dropdown-menu dropdown-menu-end">
                                <button class="dropdown-item" wire:click="edit({{ $brand->id }})"><i class="ri-pencil-line me-2"></i>Edit</button>
                                <a class="dropdown-item" href="{{ route('admin.products.index', ['brand' => $brand->id]) }}" wire:navigate><i class="ri-list-check me-2"></i>Products</a>
                                <button class="dropdown-item text-danger" x-data @click="confirmAction('Delete brand “{{ addslashes($brand->name) }}”? Products will keep existing without a brand.', () => $wire.delete({{ $brand->id }}))"><i class="ri-delete-bin-line me-2"></i>Delete</button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit brand' : 'New brand'">
        <form wire:submit="save" id="brand-form" class="row g-3">
            <div class="col-md-8"><label class="form-label">Name *</label><input class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name">@error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Order</label><input type="number" class="form-control" wire:model="form.sort_order"></div>
            <div class="col-12"><label class="form-label">Slug</label><input class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="auto">@error('form.slug')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-12"><label class="form-label">Description</label><textarea class="form-control" rows="2" wire:model="form.description"></textarea></div>
            <div class="col-12"><label class="form-label">Logo</label><input type="file" class="form-control" wire:model="logo" accept="image/*">@error('logo')<div class="text-danger fs-12">{{ $message }}</div>@enderror</div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="b-act" wire:model="form.is_active"><label class="form-check-label" for="b-act">Show on storefront</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="brand-form" class="btn btn-primary">Save brand</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
