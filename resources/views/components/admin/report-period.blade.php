{{-- Period picker for report pages (binds to the WithReportPeriod trait). Extra filters go in the slot. --}}
@props(['period', 'range'])

<div class="card mb-4">
    <div class="card-body py-3">
        <div class="d-flex flex-wrap align-items-end gap-3">
            <div>
                <label class="form-label fs-12 text-muted mb-1">Period</label>
                <select class="form-select" style="min-width: 170px" wire:model.live="period">
                    @foreach (\App\Support\Period::PRESETS as $key => $label)<option value="{{ $key }}">{{ $label }}</option>@endforeach
                </select>
            </div>
            @if ($period === 'custom')
                <div><label class="form-label fs-12 text-muted mb-1">From</label><input type="date" class="form-control" wire:model.live="from"></div>
                <div><label class="form-label fs-12 text-muted mb-1">To</label><input type="date" class="form-control" wire:model.live="to"></div>
            @endif
            {{ $slot }}
            <div class="ms-auto text-end">
                <div class="fs-12 text-muted">Showing</div>
                <div class="fw-semibold">{{ $range->label() }}</div>
            </div>
        </div>
    </div>
</div>
