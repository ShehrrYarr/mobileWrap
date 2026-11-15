<?php

use App\Models\DeviceBrand;
use App\Models\DeviceModel;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Device models')] class extends Component
{
    #[Url(except: '')]
    public string $brand = '';

    #[Url(except: '')]
    public string $search = '';

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public string $newBrand = '';

    public function mount(): void
    {
        $this->blank();
    }

    protected function blank(): void
    {
        $this->form = [
            'device_brand_id' => $this->brand ?: (string) DeviceBrand::orderBy('sort_order')->value('id'), 'name' => '', 'release_year' => now()->year,
            'front_cut_width_mm' => '', 'front_cut_height_mm' => '', 'back_cut_width_mm' => '', 'back_cut_height_mm' => '',
            'notes' => '', 'sort_order' => 0, 'is_active' => true,
        ];
        $this->resetErrorBag();
    }

    public function create(): void
    {
        $this->editingId = null;
        $this->blank();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->blank();
        $m = DeviceModel::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $m->{$k} instanceof \Stringable ? (string) $m->{$k} : ($m->{$k} ?? '');
        }
        $this->form['device_brand_id'] = (string) $m->device_brand_id;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->validate([
            'form.device_brand_id' => 'required|exists:device_brands,id',
            'form.name' => ['required', 'string', 'max:120', Rule::unique('device_models', 'name')->where('device_brand_id', $this->form['device_brand_id'])->ignore($this->editingId)],
            'form.release_year' => 'nullable|integer|min:2000|max:2100',
            'form.front_cut_width_mm' => 'nullable|numeric|min:10|max:400',
            'form.front_cut_height_mm' => 'nullable|numeric|min:10|max:400',
            'form.back_cut_width_mm' => 'nullable|numeric|min:10|max:400',
            'form.back_cut_height_mm' => 'nullable|numeric|min:10|max:400',
        ], [], ['form.name' => 'model name', 'form.device_brand_id' => 'brand']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        $model = $this->editingId ? DeviceModel::findOrFail($this->editingId) : new DeviceModel;
        $model->fill($data);
        $model->setRelation('brand', DeviceBrand::find($data['device_brand_id']));
        $model->save();

        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: $model->name.' saved.');
    }

    public function toggle(int $id): void
    {
        $m = DeviceModel::findOrFail($id);
        $m->update(['is_active' => ! $m->is_active]);
    }

    public function delete(int $id): void
    {
        $m = DeviceModel::findOrFail($id);
        if (\App\Models\LaserCutterJob::where('device_model_id', $id)->exists()) {
            $this->dispatch('toast', type: 'error', message: 'This model has laser jobs — deactivate it instead.');

            return;
        }
        $m->delete();
        $this->dispatch('toast', type: 'success', message: 'Device model deleted.');
    }

    public function addBrand(): void
    {
        $this->validate(['newBrand' => 'required|string|max:60|unique:device_brands,name'], [], ['newBrand' => 'brand name']);
        $b = DeviceBrand::create(['name' => trim($this->newBrand), 'sort_order' => DeviceBrand::count()]);
        $this->newBrand = '';
        $this->brand = (string) $b->id;
    }

    public function with(): array
    {
        return [
            'brands' => DeviceBrand::withCount('models')->orderBy('sort_order')->get(),
            'models' => DeviceModel::with('brand')->withCount('products')
                ->when($this->brand, fn ($q) => $q->where('device_brand_id', $this->brand))
                ->when($this->search, fn ($q) => $q->where('name', 'like', "%{$this->search}%"))
                ->orderBy('device_brand_id')->orderBy('sort_order')->get(),
        ];
    }
}; ?>

<div>
    <x-admin.page-header title="Device models" subtitle="One shared list for “Shop by device”, product compatibility and laser cut presets (mm).">
        <x-slot:actions>
            <button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>New model</button>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header"><h5 class="card-title mb-0">Brands</h5></div>
                <div class="list-group list-group-flush">
                    <button class="list-group-item list-group-item-action d-flex justify-content-between {{ $brand === '' ? 'active' : '' }}" wire:click="$set('brand', '')">All brands</button>
                    @foreach ($brands as $b)
                        <button class="list-group-item list-group-item-action d-flex justify-content-between {{ $brand == $b->id ? 'active' : '' }}" wire:click="$set('brand', '{{ $b->id }}')" wire:key="dbb-{{ $b->id }}">
                            {{ $b->name }} <span class="badge bg-light text-muted">{{ $b->models_count }}</span>
                        </button>
                    @endforeach
                </div>
                <div class="card-body">
                    <form wire:submit="addBrand" class="input-group input-group-sm">
                        <input type="text" class="form-control @error('newBrand') is-invalid @enderror" placeholder="New brand" wire:model="newBrand">
                        <button class="btn btn-light" type="submit"><i class="ri-add-line"></i></button>
                    </form>
                    @error('newBrand') <div class="text-danger fs-12">{{ $message }}</div> @enderror
                </div>
            </div>
        </div>
        <div class="col-lg-9">
            <div class="card">
                <div class="card-header">
                    <div class="form-icon" style="max-width: 320px">
                        <input type="search" class="form-control form-control-icon" placeholder="Search models…" wire:model.live.debounce.300ms="search">
                        <i class="ri-search-2-line text-muted"></i>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-mw mb-0">
                        <thead class="table-light"><tr><th>Model</th><th>Year</th><th>Front cut (mm)</th><th>Back cut (mm)</th><th class="text-center">Products</th><th class="text-center">Active</th><th class="text-end">Actions</th></tr></thead>
                        <tbody>
                            @forelse ($models as $m)
                                <tr wire:key="dm-{{ $m->id }}">
                                    <td><span class="fw-medium">{{ $m->name }}</span><div class="fs-12 text-muted">{{ $m->brand?->name }}</div></td>
                                    <td>{{ $m->release_year }}</td>
                                    <td class="fs-13">@if ($m->front_cut_width_mm) {{ (float) $m->front_cut_width_mm }} × {{ (float) $m->front_cut_height_mm }} @else <span class="text-muted">—</span> @endif</td>
                                    <td class="fs-13">@if ($m->back_cut_width_mm) {{ (float) $m->back_cut_width_mm }} × {{ (float) $m->back_cut_height_mm }} @else <span class="text-muted">—</span> @endif</td>
                                    <td class="text-center">{{ $m->products_count }}</td>
                                    <td class="text-center"><div class="form-check form-switch d-inline-block mb-0"><input class="form-check-input" type="checkbox" @checked($m->is_active) wire:click="toggle({{ $m->id }})"></div></td>
                                    <td class="text-end text-nowrap">
                                        <button class="btn btn-sm btn-light-primary icon-btn-sm" wire:click="edit({{ $m->id }})"><i class="ri-pencil-line"></i></button>
                                        <button class="btn btn-sm btn-light-danger icon-btn-sm" x-data @click="confirmAction('Delete {{ addslashes($m->name) }}?', () => $wire.delete({{ $m->id }}))"><i class="ri-delete-bin-line"></i></button>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="7"><x-admin.empty icon="ri-smartphone-line" title="No device models" /></td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit device model' : 'New device model'" size="lg">
        <form wire:submit="save" id="dm-form" class="row g-3">
            <div class="col-md-4">
                <label class="form-label">Brand *</label>
                <select class="form-select" wire:model="form.device_brand_id">
                    @foreach ($brands as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-5">
                <label class="form-label">Model name *</label>
                <input class="form-control @error('form.name') is-invalid @enderror" wire:model="form.name" placeholder="e.g. iPhone 17 Pro">
                @error('form.name')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>
            <div class="col-md-3">
                <label class="form-label">Release year</label>
                <input type="number" class="form-control" wire:model="form.release_year">
            </div>
            <div class="col-12"><h6 class="fs-13 mb-0 mt-2"><i class="ri-scissors-cut-line me-1"></i>Laser cut presets (millimetres)</h6><p class="fs-12 text-muted mb-0">Staff can still override these per job at the POS.</p></div>
            <div class="col-6 col-md-3"><label class="form-label fs-13">Front width</label><input type="number" step="0.1" class="form-control @error('form.front_cut_width_mm') is-invalid @enderror" wire:model="form.front_cut_width_mm"></div>
            <div class="col-6 col-md-3"><label class="form-label fs-13">Front height</label><input type="number" step="0.1" class="form-control @error('form.front_cut_height_mm') is-invalid @enderror" wire:model="form.front_cut_height_mm"></div>
            <div class="col-6 col-md-3"><label class="form-label fs-13">Back width</label><input type="number" step="0.1" class="form-control @error('form.back_cut_width_mm') is-invalid @enderror" wire:model="form.back_cut_width_mm"></div>
            <div class="col-6 col-md-3"><label class="form-label fs-13">Back height</label><input type="number" step="0.1" class="form-control @error('form.back_cut_height_mm') is-invalid @enderror" wire:model="form.back_cut_height_mm"></div>
            <div class="col-md-9"><label class="form-label">Notes</label><input class="form-control" wire:model="form.notes" placeholder="e.g. template name in cutter software"></div>
            <div class="col-md-3"><label class="form-label">Order</label><input type="number" class="form-control" wire:model="form.sort_order"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="dm-act" wire:model="form.is_active"><label class="form-check-label" for="dm-act">Active</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="dm-form" class="btn btn-primary">Save model</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
