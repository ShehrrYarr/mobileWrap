<?php

namespace App\Support;

use Illuminate\Support\Carbon;

/** A reporting date range (whole days, store timezone) with presets and a matching previous period. */
class Period
{
    public const PRESETS = [
        'today' => 'Today',
        'yesterday' => 'Yesterday',
        'this_week' => 'This week',
        'last_7' => 'Last 7 days',
        'this_month' => 'This month',
        'last_month' => 'Last month',
        'last_30' => 'Last 30 days',
        'this_year' => 'This year',
        'custom' => 'Custom dates',
    ];

    public function __construct(public Carbon $from, public Carbon $to, public string $key = 'custom') {}

    public static function make(?string $key, ?string $from = null, ?string $to = null): self
    {
        $now = now();

        return match ($key) {
            'today' => new self($now->copy()->startOfDay(), $now->copy()->endOfDay(), 'today'),
            'yesterday' => new self($now->copy()->subDay()->startOfDay(), $now->copy()->subDay()->endOfDay(), 'yesterday'),
            'this_week' => new self($now->copy()->startOfWeek(Carbon::MONDAY), $now->copy()->endOfDay(), 'this_week'),
            'last_7' => new self($now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay(), 'last_7'),
            'last_month' => new self($now->copy()->subMonthNoOverflow()->startOfMonth(), $now->copy()->subMonthNoOverflow()->endOfMonth(), 'last_month'),
            'last_30' => new self($now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay(), 'last_30'),
            'this_year' => new self($now->copy()->startOfYear(), $now->copy()->endOfDay(), 'this_year'),
            'custom' => self::custom($from, $to),
            default => new self($now->copy()->startOfMonth(), $now->copy()->endOfDay(), 'this_month'),
        };
    }

    protected static function custom(?string $from, ?string $to): self
    {
        try {
            $f = $from ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
            $t = $to ? Carbon::parse($to)->endOfDay() : now()->endOfDay();
        } catch (\Throwable) {
            return self::make('this_month');
        }
        if ($f->gt($t)) {
            [$f, $t] = [$t->copy()->startOfDay(), $f->copy()->endOfDay()];
        }

        return new self($f, $t, 'custom');
    }

    public function days(): int
    {
        return (int) $this->from->copy()->startOfDay()->diffInDays($this->to->copy()->startOfDay()) + 1;
    }

    /** Same length, immediately before this one (for "vs previous period"). */
    public function previous(): self
    {
        $days = $this->days();
        $to = $this->from->copy()->subDay()->endOfDay();

        return new self($to->copy()->subDays($days - 1)->startOfDay(), $to, 'previous');
    }

    public function label(): string
    {
        if ($this->from->isSameDay($this->to)) {
            return $this->from->format('d M Y');
        }

        return $this->from->format($this->from->year === $this->to->year ? 'd M' : 'd M Y').' – '.$this->to->format('d M Y');
    }

    /** Day-by-day for up to ~2 months, month-by-month beyond that. */
    public function groupsByMonth(): bool
    {
        return $this->days() > 62;
    }

    /** ['2026-11-01' => '01 Nov', …] or ['2026-11' => 'Nov 2026', …] */
    public function buckets(): array
    {
        $out = [];
        $cursor = $this->from->copy()->startOfDay();
        if ($this->groupsByMonth()) {
            $cursor->startOfMonth();
            while ($cursor->lte($this->to)) {
                $out[$cursor->format('Y-m')] = $cursor->format('M Y');
                $cursor->addMonthNoOverflow();
            }
        } else {
            while ($cursor->lte($this->to)) {
                $out[$cursor->format('Y-m-d')] = $cursor->format('D, d M');
                $cursor->addDay();
            }
        }

        return $out;
    }

    public function fileSlug(): string
    {
        return $this->from->isSameDay($this->to)
            ? $this->from->format('Y-m-d')
            : $this->from->format('Y-m-d').'_to_'.$this->to->format('Y-m-d');
    }
}
