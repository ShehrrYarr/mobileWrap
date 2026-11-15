<?php

use App\Models\Attribute;
use App\Models\AttributeValue;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Variant options')] class extends Component
{
    public string $newAttribute = '';

    public array $newValue = [];

    public function addAttribute(): void
    {
        $this->validate(['newAttribute' => 'required|string|max:60|unique:attributes,name'], [], ['newAttribute' => 'option name']);
        Attribute::create(['name' => trim($this->newAttribute), 'sort_order' => Attribute::count()]);
        $this->newAttribute = '';
        $this->dispatch('toast', type: 'success', message: 'Option added.');
    }

    public function addValue(int $attributeId): void
    {
        $value = trim($this->newValue[$attributeId] ?? '');
        if ($value === '') {
            return;
        }
        $attr = Attribute::findOrFail($attributeId);
        $attr->values()->firstOrCreate(['value' => $value], ['sort_order' => $attr->values()->count()]);
        $this->newValue[$attributeId] = '';
    }

    public function deleteValue(int $id): void
    {
        $value = AttributeValue::withCount('variants')->findOrFail($id);
        if ($value->variants_count) {
            $this->dispatch('toast', type: 'error', message: "“{$value->value}” is used by {$value->variants_count} variant(s).");

            return;
        }
        $value->delete();
    }

    public function deleteAttribute(int $id): void
    {
        $attr = Attribute::with('values')->findOrFail($id);
        if ($attr->values->contains(fn ($v) => $v->variants()->exists())) {
            $this->dispatch('toast', type: 'error', message: 'This option is used by product variants.');

            return;
        }
        $attr->delete();
        $this->dispatch('toast', type: 'success', message: 'Option deleted.');
    }

    public function with(): array
    {
        return ['attributes' => Attribute::with(['values' => fn ($q) => $q->withCount('variants')])->orderBy('sort_order')->get()];
    }
}; ?>

<div>
    <x-admin.page-header title="Variant options" :crumbs="['Products' => route('admin.products.index')]" subtitle="Options beyond colour that create variants — e.g. Capacity, Length, Wattage. Colours are set per product." />

    <div class="row g-4">
        @foreach ($attributes as $attr)
            <div class="col-md-6 col-xl-4" wire:key="attr-{{ $attr->id }}">
                <div class="card mb-0 h-100">
                    <div class="card-header d-flex align-items-center">
                        <h5 class="card-title mb-0 flex-grow-1">{{ $attr->name }}</h5>
                        <button class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete option “{{ $attr->name }}”?', () => $wire.deleteAttribute({{ $attr->id }}))"><i class="ri-delete-bin-line"></i></button>
                    </div>
                    <div class="card-body">
                        <div class="d-flex flex-wrap gap-2 mb-3">
                            @forelse ($attr->values as $val)
                                <span class="badge bg-light text-body border fs-13 fw-normal d-inline-flex align-items-center gap-1 py-2 px-2">
                                    {{ $val->value }} <span class="text-muted fs-11">({{ $val->variants_count }})</span>
                                    <button type="button" class="btn btn-link p-0 text-muted lh-1" wire:click="deleteValue({{ $val->id }})" title="Remove"><i class="ri-close-line"></i></button>
                                </span>
                            @empty
                                <span class="text-muted fs-13">No values yet.</span>
                            @endforelse
                        </div>
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" placeholder="New value, e.g. 30000mAh" wire:model="newValue.{{ $attr->id }}" wire:keydown.enter="addValue({{ $attr->id }})">
                            <button class="btn btn-primary" wire:click="addValue({{ $attr->id }})"><i class="ri-add-line"></i></button>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach

        <div class="col-md-6 col-xl-4">
            <div class="card mb-0 h-100 border-dashed">
                <div class="card-body d-flex flex-column justify-content-center">
                    <h6 class="mb-2">Add an option type</h6>
                    <form wire:submit="addAttribute" class="input-group">
                        <input type="text" class="form-control @error('newAttribute') is-invalid @enderror" placeholder="e.g. Storage, Size, Material" wire:model="newAttribute">
                        <button class="btn btn-primary" type="submit">Add</button>
                    </form>
                    @error('newAttribute') <div class="text-danger fs-12 mt-1">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
    </div>
</div>
