<?php

namespace App\Livewire;

use App\Enums\StatsPeriod;
use App\Services\StatsService;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.gamehub')]
#[Title('Stats')]
class Stats extends Component
{
    #[Url(except: 'month')]
    public string $period = 'month';

    public function setPeriod(string $period): void
    {
        $this->period = StatsPeriod::tryFrom($period)?->value ?? 'month';
    }

    public function render(StatsService $stats)
    {
        $period = StatsPeriod::tryFrom($this->period) ?? StatsPeriod::Month;
        $report = $stats->report((int) Auth::id(), $period);

        return view('livewire.stats', [
            'periods' => StatsPeriod::cases(),
            'report' => $report,
            'change' => StatsService::change($report['minutes'], $report['previousMinutes']),
        ]);
    }
}
