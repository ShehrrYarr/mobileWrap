{{-- One Profit & loss line: $label, $now, $before, $invert (a rise is bad, e.g. costs) --}}
@php
    $change = \App\Services\ReportService::change((float) $now, (float) $before);
    $good = $change === null ? null : ($invert ? $change < 0 : $change > 0);
@endphp
<tr>
    <td class="ps-4">{{ $label }}</td>
    <td class="text-end">{{ money($now) }}</td>
    <td class="text-end text-muted">{{ money($before) }}</td>
    <td class="text-end fs-13 {{ $change === null || abs($change) < 0.05 ? 'text-muted' : ($good ? 'text-success' : 'text-danger') }}">{{ \App\Services\ReportService::pct($change) }}</td>
</tr>
