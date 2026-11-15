<?php

namespace App\Livewire\Concerns;

use App\Support\Period;
use Livewire\Attributes\Url;

/** Period picker state for report pages (kept in the URL so links and PDFs match the screen). */
trait WithReportPeriod
{
    #[Url(except: 'this_month')]
    public string $period = 'this_month';

    #[Url(except: '')]
    public string $from = '';

    #[Url(except: '')]
    public string $to = '';

    public function updatedPeriod(): void
    {
        if ($this->period === 'custom') {
            if (! $this->from || ! $this->to) {
                $p = Period::make('this_month');
                $this->from = $p->from->toDateString();
                $this->to = $p->to->toDateString();
            }
        } else {
            $this->from = '';
            $this->to = '';
        }
    }

    protected function periodRange(): Period
    {
        return Period::make($this->period, $this->from ?: null, $this->to ?: null);
    }

    /** Query string for the matching PDF download. */
    protected function periodQuery(array $extra = []): array
    {
        return array_filter(['period' => $this->period, 'from' => $this->period === 'custom' ? $this->from : null,
            'to' => $this->period === 'custom' ? $this->to : null] + $extra, fn ($v) => $v !== null && $v !== '');
    }
}
