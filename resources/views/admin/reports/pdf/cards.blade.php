{{-- Summary cards, four per row. $cards = [[label, value, sub|null], …] --}}
<table class="cards" style="margin-top: 10px">
    @foreach (array_chunk($cards, 4) as $row)
        <tr>
            @foreach ($row as [$label, $value, $sub])
                <td class="card"><div class="k">{{ $label }}</div><div class="v">{{ $value }}</div>@if ($sub)<div class="s">{!! $sub !!}</div>@endif</td>
            @endforeach
            @for ($i = count($row); $i < 4; $i++)<td style="width: 25%"></td>@endfor
        </tr>
        @unless ($loop->last)<tr><td colspan="4" style="height: 6px"></td></tr>@endunless
    @endforeach
</table>
