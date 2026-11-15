<?php

use App\Models\LaserCutterJob;
use App\Services\LaserService;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;

new #[Layout('admin.layouts.app')] #[Title('Laser cutter queue')] class extends Component
{
    #[Url(except: '')]
    public string $search = '';

    #[Url]
    public string $date = '';

    public ?int $failingId = null;

    public string $failReason = '';

    public function mount(): void
    {
        $this->date = $this->date ?: now()->toDateString();
    }

    protected function act(int $id, callable $action, string $message): void
    {
        try {
            $action(LaserCutterJob::findOrFail($id));
            $this->dispatch('toast', type: 'success', message: $message);
        } catch (ValidationException $e) {
            $this->dispatch('toast', type: 'error', message: collect($e->errors())->flatten()->first());
        }
    }

    public function start(int $id, LaserService $laser): void
    {
        $this->act($id, fn ($job) => $laser->start($job), 'Cutting started.');
    }

    public function complete(int $id, LaserService $laser): void
    {
        $this->act($id, fn ($job) => $laser->complete($job), 'Job completed — ready for the customer.');
    }

    public function askFail(int $id): void
    {
        $this->failingId = $id;
        $this->failReason = '';
    }

    public function fail(LaserService $laser): void
    {
        $this->validate(['failReason' => 'nullable|string|max:255']);
        $this->act($this->failingId, fn ($job) => $laser->fail($job, $this->failReason ?: null), 'Job marked failed.');
        $this->failingId = null;
    }

    public function recut(int $id, LaserService $laser): void
    {
        $this->act($id, fn ($job) => $laser->recut($job), 'Re-cut queued; spoiled material logged as wastage.');
    }

    public function cancel(int $id, LaserService $laser): void
    {
        $this->act($id, fn ($job) => $laser->cancel($job, true), 'Job cancelled and material returned to stock.');
    }

    public function with(): array
    {
        $base = LaserCutterJob::with(['deviceModel.brand', 'material.product', 'material.color', 'order:id,number', 'customer:id,name,phone', 'operator:id,name', 'parent:id,number', 'recuts:id,parent_id,number'])
            ->when($this->search, fn ($q) => $q->where(fn ($q) => $q->where('number', 'like', "%{$this->search}%")
                ->orWhereHas('order', fn ($o) => $o->where('number', 'like', "%{$this->search}%"))
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', "%{$this->search}%")->orWhere('phone', 'like', "%{$this->search}%"))));

        $day = fn ($q) => $q->whereDate('completed_at', $this->date);

        return [
            'columns' => [
                'queued' => ['Queued', 'secondary', 'ri-time-line', (clone $base)->where('status', 'queued')->orderByDesc('priority')->oldest('id')->get()],
                'cutting' => ['Cutting', 'primary', 'ri-scissors-cut-line', (clone $base)->where('status', 'cutting')->oldest('started_at')->get()],
                'completed' => ['Completed', 'success', 'ri-checkbox-circle-line', $day((clone $base)->where('status', 'completed'))->latest('completed_at')->get()],
                'failed' => ['Failed / cancelled', 'danger', 'ri-error-warning-line', $day((clone $base)->whereIn('status', ['failed', 'cancelled']))->latest('completed_at')->get()],
            ],
            'materialToday' => (float) LaserCutterJob::whereDate('created_at', $this->date)->sum('total_area_cm2'),
            'feesToday' => (float) LaserCutterJob::whereDate('created_at', $this->date)->sum('service_fee'),
        ];
    }
}; ?>

<div wire:poll.10s>
    <x-admin.page-header title="Laser cutter queue" subtitle="Jobs arrive here when a POS sale with a laser cut is paid. Material is deducted from the roll at that moment.">
        <x-slot:actions>
            <span class="badge bg-light text-body fs-13 py-2 px-3"><i class="ri-ruler-2-line me-1"></i>{{ qty($materialToday) }} cm² used</span>
            <span class="badge bg-success-subtle text-success fs-13 py-2 px-3">{{ money($feesToday) }} in fees</span>
            @can('pos.use')<a href="{{ route('admin.pos') }}" class="btn btn-sm btn-primary"><i class="ri-computer-line me-1"></i>POS</a>@endcan
        </x-slot:actions>
    </x-admin.page-header>

    <div class="row g-2 mb-3">
        <div class="col-md-5"><div class="form-icon"><input type="search" class="form-control form-control-icon" placeholder="Job #, invoice #, customer or phone" wire:model.live.debounce.300ms="search"><i class="ri-search-2-line text-muted"></i></div></div>
        <div class="col-md-3"><input type="date" class="form-control" wire:model.live="date" title="Day shown for completed and failed jobs"></div>
    </div>

    <div class="row g-3">
        @foreach ($columns as $status => [$label, $color, $icon, $jobs])
            <div class="col-md-6 col-xl-3">
                <div class="card mb-0 h-100" style="background: var(--bs-tertiary-bg)">
                    <div class="card-header d-flex align-items-center bg-transparent">
                        <span class="badge bg-{{ $color }} me-2"><i class="{{ $icon }}"></i></span>
                        <h6 class="mb-0 flex-grow-1">{{ $label }}</h6>
                        <span class="badge bg-light text-muted">{{ $jobs->count() }}</span>
                    </div>
                    <div class="card-body d-flex flex-column gap-2 p-2" style="min-height: 240px">
                        @forelse ($jobs as $job)
                            <div class="card mb-0 shadow-none border" wire:key="job-{{ $job->id }}">
                                <div class="card-body p-3">
                                    <div class="d-flex align-items-start gap-2 mb-2">
                                        <div class="flex-grow-1">
                                            <div class="fw-bold">{{ $job->number }} @if ($job->attempt > 1)<span class="badge bg-warning-subtle text-warning ms-1">Re-cut #{{ $job->attempt }}</span>@endif</div>
                                            <div class="fs-12 text-muted">{{ $job->order?->number }} · {{ $job->created_at->diffForHumans() }}</div>
                                        </div>
                                        <span class="badge bg-dark">{{ $job->sideLabel() }}</span>
                                    </div>
                                    <div class="fw-semibold"><i class="ri-smartphone-line me-1"></i>{{ $job->deviceModel?->full_name }}</div>
                                    <div class="d-flex align-items-center gap-2 fs-13 mt-1">
                                        @if ($job->material?->color?->hex_code)<span class="mw-swatch" style="width: 16px; height: 16px; background: {{ $job->material->color->hex_code }}"></span>@endif
                                        <span>{{ $job->material?->product?->name }} — {{ $job->material?->label }}</span>
                                    </div>
                                    <div class="fs-13 mt-1"><i class="ri-ruler-2-line me-1 text-muted"></i>{{ (float) $job->cut_width_mm }} × {{ (float) $job->cut_height_mm }} mm · {{ $job->quantity }} pc · {{ qty($job->total_area_cm2) }} cm²</div>
                                    @if ($job->customer)<div class="fs-13 mt-1"><i class="ri-user-3-line me-1 text-muted"></i>{{ $job->customer->name }} {{ $job->customer->phone }}</div>@endif
                                    @if ($job->notes)<div class="alert alert-warning py-1 px-2 fs-12 mt-2 mb-0">{{ $job->notes }}</div>@endif
                                    @if ($job->failure_reason && $job->status === 'failed')<div class="text-danger fs-12 mt-2"><i class="ri-error-warning-line"></i> {{ $job->failure_reason }}</div>@endif
                                    @if ($job->operator)<div class="fs-12 text-muted mt-2">Operator: {{ $job->operator->name }}@if ($job->completed_at) · {{ $job->completed_at->format('h:i a') }}@endif</div>@endif

                                    <div class="d-flex flex-wrap gap-2 mt-3">
                                        @if ($job->status === 'queued')
                                            <button type="button" class="btn btn-sm btn-primary flex-grow-1" wire:click="start({{ $job->id }})"><i class="ri-play-line me-1"></i>Start</button>
                                            <button type="button" class="btn btn-sm btn-success" wire:click="complete({{ $job->id }})" title="Mark done"><i class="ri-check-line"></i></button>
                                            <button type="button" class="btn btn-sm btn-light" x-data @click="confirmAction('Cancel {{ $job->number }}? The material goes back into stock.', () => $wire.cancel({{ $job->id }}), { confirmText: 'Cancel job' })" title="Cancel"><i class="ri-close-line"></i></button>
                                        @elseif ($job->status === 'cutting')
                                            <button type="button" class="btn btn-sm btn-success flex-grow-1" wire:click="complete({{ $job->id }})"><i class="ri-check-double-line me-1"></i>Done</button>
                                            <button type="button" class="btn btn-sm btn-light-danger" wire:click="askFail({{ $job->id }})"><i class="ri-error-warning-line me-1"></i>Failed</button>
                                        @elseif ($job->status === 'failed' && $job->recuts->isNotEmpty())
                                            <span class="fs-12 text-muted"><i class="ri-restart-line"></i> Re-cut as {{ $job->recuts->pluck('number')->implode(', ') }}</span>
                                        @elseif ($job->status === 'failed')
                                            <button type="button" class="btn btn-sm btn-warning flex-grow-1" x-data @click="confirmAction('Queue a re-cut? Another {{ qty($job->total_area_cm2) }} cm² will be taken from stock as wastage.', () => $wire.recut({{ $job->id }}), { icon: 'question', confirmText: 'Re-cut' })"><i class="ri-restart-line me-1"></i>Re-cut</button>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @empty
                            <div class="text-center text-muted fs-13 py-5">Nothing here.</div>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <x-admin.modal :show="(bool) $failingId" close="$set('failingId', null)" title="Mark job as failed">
        <label class="form-label">What went wrong?</label>
        <input type="text" class="form-control" wire:model="failReason" placeholder="e.g. film lifted at the corner, misaligned camera hole" wire:keydown.enter="fail">
        <p class="fs-12 text-muted mt-2 mb-0">You can queue a re-cut from the Failed column afterwards.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-light" wire:click="$set('failingId', null)">Back</button>
            <button type="button" class="btn btn-danger" wire:click="fail">Mark failed</button>
        </x-slot:footer>
    </x-admin.modal>
</div>
