<?php

use App\Models\Category;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Categories')] class extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public $image = null;

    public function mount(): void
    {
        $this->resetForm();
    }

    protected function resetForm(): void
    {
        $this->form = ['parent_id' => '', 'name' => '', 'slug' => '', 'icon' => 'ri-price-tag-3-line', 'description' => '', 'sort_order' => 0, 'is_active' => true, 'is_featured' => false, 'show_in_menu' => true, 'image' => null];
        $this->image = null;
        $this->resetErrorBag();
    }

    public function create(?int $parentId = null): void
    {
        $this->resetForm();
        $this->editingId = null;
        $this->form['parent_id'] = (string) ($parentId ?? '');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->resetForm();
        $cat = Category::findOrFail($id);
        $this->editingId = $cat->id;
        $this->form = array_merge($this->form, $cat->only(array_keys($this->form)));
        $this->form['parent_id'] = (string) ($cat->parent_id ?? '');
        $this->form['description'] = (string) $cat->description;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.name' => 'required|string|max:120',
            'form.slug' => ['nullable', 'alpha_dash', Rule::unique('categories', 'slug')->ignore($this->editingId)],
            'form.parent_id' => ['nullable', 'exists:categories,id', Rule::notIn([$this->editingId])],
            'form.icon' => 'nullable|string|max:60',
            'form.sort_order' => 'integer|min:0',
            'image' => 'nullable|image|max:3072',
        ], [], ['form.name' => 'name', 'form.parent_id' => 'parent']);

        $data = $this->form;
        $data['parent_id'] = $data['parent_id'] ?: null;
        $data['slug'] = $data['slug'] ?: null;
        if ($this->image) {
            $data['image'] = $this->image->store('categories', 'uploads');
        }

        Category::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Category saved.');
    }

    public function toggle(int $id): void
    {
        $cat = Category::findOrFail($id);
        $cat->update(['is_active' => ! $cat->is_active]);
    }

    public function delete(int $id): void
    {
        $cat = Category::withCount(['products', 'children'])->findOrFail($id);
        if ($cat->products_count || $cat->children_count) {
            $this->dispatch('toast', type: 'error', message: 'Move its products and sub-categories first.');

            return;
        }
        $cat->delete();
        $this->dispatch('toast', type: 'success', message: 'Category deleted.');
    }

    public function with(): array
    {
        return [
            'categories' => Category::roots()->withCount('products')->with(['children' => fn ($q) => $q->withCount('products')])->orderBy('sort_order')->get(),
            'parents' => Category::roots()->orderBy('sort_order')->get(),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Categories" :crumbs="['Products' => route('admin.products.index')]" subtitle="Organise products into categories and sub-categories. Featured ones appear on the storefront homepage.">
        <x-slot:actions>
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>New category</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover table-mw mb-0">
                <thead class="table-light"><tr><th>Category</th><th>Slug</th><th class="text-center">Products</th><th class="text-center">Featured</th><th class="text-center">In menu</th><th class="text-center">Active</th><th class="text-end">Actions</th></tr></thead>
                <tbody>
                    @foreach ($categories as $cat)
                        @foreach (collect([$cat])->merge($cat->children) as $row)
                            <tr wire:key="cat-{{ $row->id }}">
                                <td>
                                    <div class="d-flex align-items-center gap-3 {{ $row->parent_id ? 'ps-5' : '' }}">
                                        @if ($row->parent_id)<i class="ri-corner-down-right-line text-muted"></i>@endif
                                        @if ($row->image)
                                            <img src="{{ upload_url($row->image) }}" class="mw-thumb" alt="">
                                        @else
                                            <span class="mw-thumb d-inline-flex align-items-center justify-content-center"><i class="{{ $row->icon ?: 'ri-price-tag-3-line' }} fs-18 text-muted"></i></span>
                                        @endif
                                        <span class="fw-medium">{{ $row->name }}</span>
                                    </div>
                                </td>
                                <td class="text-muted fs-13">{{ $row->slug }}</td>
                                <td class="text-center">{{ $row->products_count }}</td>
                                <td class="text-center">@if ($row->is_featured)<i class="ri-star-fill text-warning"></i>@endif</td>
                                <td class="text-center">@if ($row->show_in_menu)<i class="ri-check-line text-success"></i>@endif</td>
                                <td class="text-center"><div class="form-check form-switch d-inline-block mb-0"><input type="checkbox" class="form-check-input" @checked($row->is_active) wire:click="toggle({{ $row->id }})"></div></td>
                                <td class="text-end text-nowrap">
                                    @unless ($row->parent_id)
                                        <button class="btn btn-sm btn-light icon-btn-sm" wire:click="create({{ $row->id }})" title="Add sub-category"><i class="ri-add-line"></i></button>
                                    @endunless
                                    <button class="btn btn-sm btn-light-primary icon-btn-sm" wire:click="edit({{ $row->id }})" title="Edit"><i class="ri-pencil-line"></i></button>
                                    <button class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete “{{ addslashes($row->name) }}”?', () => $wire.delete({{ $row->id }}))" title="Delete"><i class="ri-delete-bin-line"></i></button>
                                </td>
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit category' : 'New category'">
        <form wire:submit="save" id="cat-form" class="row g-3">
            <div class="col-md-7">
                <label class="form-label">Name <span class="text-danger">*</span></label>
                <input type="text" class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name">
                @error('form.name') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-5">
                <label class="form-label">Parent</label>
                <select class="form-select @error('form.parent_id') is-invalid @enderror" wire:model="form.parent_id">
                    <option value="">— Top level —</option>
                    @foreach ($parents as $p)
                        @if ($p->id !== $editingId)<option value="{{ $p->id }}">{{ $p->name }}</option>@endif
                    @endforeach
                </select>
            </div>
            <div class="col-md-6">
                <label class="form-label">Slug</label>
                <input type="text" class="form-control @error('form.slug') is-invalid @enderror" wire:model="form.slug" placeholder="auto">
                @error('form.slug') <div class="invalid-feedback">{{ $message }}</div> @enderror
            </div>
            <div class="col-md-4">
                <label class="form-label">Icon class</label>
                <div class="input-group"><span class="input-group-text"><i class="{{ $form['icon'] }}"></i></span><input type="text" class="form-control" wire:model.live.debounce.400ms="form.icon"></div>
            </div>
            <div class="col-md-2">
                <label class="form-label">Order</label>
                <input type="number" class="form-control" wire:model="form.sort_order" min="0">
            </div>
            <div class="col-12">
                <label class="form-label">Description</label>
                <textarea class="form-control" rows="2" wire:model="form.description"></textarea>
            </div>
            <div class="col-12">
                <label class="form-label">Cover image</label>
                <div class="d-flex gap-3 align-items-center">
                    @if ($image)
                        <img src="{{ $image->temporaryUrl() }}" class="mw-thumb mw-thumb-lg" alt="">
                    @elseif ($form['image'])
                        <img src="{{ upload_url($form['image']) }}" class="mw-thumb mw-thumb-lg" alt="">
                    @endif
                    <input type="file" class="form-control" wire:model="image" accept="image/*">
                </div>
                @error('image') <div class="text-danger fs-12">{{ $message }}</div> @enderror
                <div class="form-text fs-12">Leave empty to use the first product image automatically.</div>
            </div>
            <div class="col-12 d-flex flex-wrap gap-4">
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-act" wire:model="form.is_active"><label class="form-check-label" for="c-act">Active</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-feat" wire:model="form.is_featured"><label class="form-check-label" for="c-feat">Featured on homepage</label></div>
                <div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="c-menu" wire:model="form.show_in_menu"><label class="form-check-label" for="c-menu">Show in store menu</label></div>
            </div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="cat-form" class="btn btn-primary" wire:loading.attr="disabled">Save category</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
