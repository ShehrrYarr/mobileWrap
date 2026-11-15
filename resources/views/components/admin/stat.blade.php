{{-- Report stat card. $change: % vs previous period (null = nothing to compare); $invert: a rise is bad (e.g. returns). --}}
@props(['label', 'value', 'sub' => null, 'change' => null, 'invert' => false, 'tone' => null])

<div class="card mb-0 h-100">
    <div class="card-body">
        <div class="fs-12 text-muted mb-1">{{ $label }}</div>
        <h4 class="mb-1 {{ $tone ? 'text-'.$tone : '' }}">{{ $value }}</h4>
        <div class="fs-12">
            @if ($change !== null)
                @php $good = $invert ? $change < 0 : $change > 0; @endphp
                <span class="{{ abs($change) < 0.05 ? 'text-muted' : ($good ? 'text-success' : 'text-danger') }}">
                    <i class="{{ $change >= 0 ? 'ri-arrow-up-line' : 'ri-arrow-down-line' }}"></i>{{ \App\Services\ReportService::pct($change) }}
                </span>
                <span class="text-muted">vs previous</span>
            @endif
            @if ($sub)<span class="text-muted">{{ $sub }}</span>@endif
        </div>
    </div>
</div>
