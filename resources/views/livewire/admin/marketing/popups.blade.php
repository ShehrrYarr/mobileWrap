<?php

use App\Models\Popup;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;
use Livewire\WithFileUploads;

new #[Layout('admin.layouts.app')] #[Title('Popups')] class extends Component
{
    use WithFileUploads;

    public bool $showForm = false;

    public ?int $editingId = null;

    public array $form = [];

    public $image = null;

    public function create(): void
    {
        $this->editingId = null;
        $this->image = null;
        $this->form = ['title' => '', 'type' => 'discount', 'content' => '', 'button_text' => 'Shop now', 'button_url' => '/shop', 'coupon_code' => '', 'display_on' => 'home', 'delay_seconds' => 3, 'frequency' => 'once_per_session', 'starts_at' => '', 'ends_at' => '', 'is_active' => true, 'image' => null];
        $this->resetErrorBag();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->create();
        $p = Popup::findOrFail($id);
        $this->editingId = $id;
        foreach (array_keys($this->form) as $k) {
            $this->form[$k] = $p->{$k} ?? '';
        }
        $this->form['starts_at'] = $p->starts_at?->format('Y-m-d\TH:i') ?? '';
        $this->form['ends_at'] = $p->ends_at?->format('Y-m-d\TH:i') ?? '';
    }

    public function save(): void
    {
        $this->validate([
            'form.title' => 'required|string|max:120',
            'form.type' => 'required|in:announcement,newsletter,discount',
            'form.content' => 'nullable|string|max:1000',
            'form.button_url' => 'nullable|string|max:255',
            'form.coupon_code' => 'nullable|string|max:40',
            'form.delay_seconds' => 'integer|min:0|max:120',
            'form.display_on' => 'in:all,home',
            'form.frequency' => 'in:once_per_session,once_per_day,always',
            'form.ends_at' => 'nullable|date',
            'image' => 'nullable|image|max:3072',
        ], [], ['form.title' => 'title']);

        $data = collect($this->form)->map(fn ($v) => $v === '' ? null : $v)->all();
        if ($this->image) {
            $data['image'] = $this->image->store('popups', 'uploads');
        }
        Popup::updateOrCreate(['id' => $this->editingId], $data);
        $this->showForm = false;
        $this->dispatch('toast', type: 'success', message: 'Popup saved.');
    }

    public function toggle(int $id): void
    {
        $p = Popup::findOrFail($id);
        $p->update(['is_active' => ! $p->is_active]);
    }

    public function delete(int $id): void
    {
        Popup::findOrFail($id)->delete();
    }

    public function with(): array
    {
        return ['popups' => Popup::latest('id')->get()];
    }
}; ?>

<div>
    <x-admin.page-header title="Popups" subtitle="Announcements, newsletter sign-ups and discount offers shown on the storefront.">
        <x-slot:actions><button class="btn btn-sm btn-primary" wire:click="create"><i class="ri-add-line me-1"></i>New popup</button></x-slot:actions>
    </x-admin.page-header>

    <div class="row g-4">
        @forelse ($popups as $p)
            <div class="col-md-6 col-xl-4" wire:key="pp-{{ $p->id }}">
                <div class="card mb-0 h-100">
                    <div class="card-body">
                        <div class="d-flex mb-2 gap-2 align-items-center">
                            <span class="badge bg-primary-subtle text-primary">{{ ucfirst($p->type) }}</span>
                            <span class="badge bg-light text-muted">{{ $p->display_on === 'home' ? 'Homepage' : 'All pages' }}</span>
                            <div class="ms-auto form-check form-switch mb-0"><input type="checkbox" class="form-check-input" @checked($p->is_active) wire:click="toggle({{ $p->id }})"></div>
                        </div>
                        <h5 class="mb-1">{{ $p->title }}</h5>
                        <p class="fs-13 text-muted mb-2">{{ Str::limit($p->content, 120) }}</p>
                        @if ($p->coupon_code)<span class="badge bg-dark font-monospace">{{ $p->coupon_code }}</span>@endif
                        <div class="fs-12 text-muted mt-2">After {{ $p->delay_seconds }}s · {{ str_replace('_', ' ', $p->frequency) }}</div>
                    </div>
                    <div class="card-footer d-flex gap-2">
                        <button class="btn btn-sm btn-light-primary" wire:click="edit({{ $p->id }})"><i class="ri-pencil-line me-1"></i>Edit</button>
                        <button class="btn btn-sm btn-light-danger ms-auto" x-data @click="confirmAction('Delete this popup?', () => $wire.delete({{ $p->id }}))"><i class="ri-delete-bin-line"></i></button>
                    </div>
                </div>
            </div>
        @empty
            <div class="col-12"><div class="card"><x-admin.empty icon="ri-chat-smile-2-line" title="No popups yet" /></div></div>
        @endforelse
    </div>

    <x-admin.modal :show="$showForm" :title="$editingId ? 'Edit popup' : 'New popup'" size="lg">
        <form wire:submit="save" id="pp-form" class="row g-3">
            <div class="col-md-8"><label class="form-label">Title *</label><input class="form-control @error('form.title') is-invalid @enderror" wire:model="form.title">@error('form.title')<div class="invalid-feedback">{{ $message }}</div>@enderror</div>
            <div class="col-md-4"><label class="form-label">Type</label><select class="form-select" wire:model.live="form.type"><option value="discount">Discount offer</option><option value="newsletter">Newsletter signup</option><option value="announcement">Announcement</option></select></div>
            <div class="col-12"><label class="form-label">Message</label><textarea class="form-control" rows="3" wire:model="form.content"></textarea></div>
            @if (($form['type'] ?? '') === 'discount')
                <div class="col-md-4"><label class="form-label">Coupon code</label><input class="form-control font-monospace text-uppercase" wire:model="form.coupon_code"></div>
            @endif
            @if (($form['type'] ?? '') !== 'newsletter')
                <div class="col-md-4"><label class="form-label">Button text</label><input class="form-control" wire:model="form.button_text"></div>
                <div class="col-md-4"><label class="form-label">Button link</label><input class="form-control" wire:model="form.button_url"></div>
            @endif
            <div class="col-md-4"><label class="form-label">Show on</label><select class="form-select" wire:model="form.display_on"><option value="home">Homepage only</option><option value="all">All pages</option></select></div>
            <div class="col-md-4"><label class="form-label">Delay (seconds)</label><input type="number" min="0" class="form-control" wire:model="form.delay_seconds"></div>
            <div class="col-md-4"><label class="form-label">Frequency</label><select class="form-select" wire:model="form.frequency"><option value="once_per_session">Once per visit</option><option value="once_per_day">Once per day</option><option value="always">Every page load</option></select></div>
            <div class="col-md-6"><label class="form-label">Starts</label><input type="datetime-local" class="form-control" wire:model="form.starts_at"></div>
            <div class="col-md-6"><label class="form-label">Ends</label><input type="datetime-local" class="form-control" wire:model="form.ends_at"></div>
            <div class="col-12"><label class="form-label">Image (optional)</label><input type="file" class="form-control" wire:model="image" accept="image/*"></div>
            <div class="col-12"><div class="form-check form-switch"><input class="form-check-input" type="checkbox" id="pp-act" wire:model="form.is_active"><label class="form-check-label" for="pp-act">Active</label></div></div>
        </form>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('showForm', false)">Cancel</button>
            <button type="submit" form="pp-form" class="btn btn-primary">Save popup</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
