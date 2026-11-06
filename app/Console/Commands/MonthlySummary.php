<?php

namespace App\Console\Commands;

use App\Enums\StatsPeriod;
use App\Models\User;
use App\Services\StatsService;
use App\Support\Format;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class MonthlySummary extends Command
{
    protected $signature = 'gamehub:monthly-summary {--user= : Only this user id}';

    protected $description = 'Email each user a summary of the last 30 days';

    public function handle(StatsService $stats): int
    {
        $users = User::query()->when($this->option('user'), fn ($q, $id) => $q->whereKey($id))->get();

        foreach ($users as $user) {
            $report = $stats->report($user->id, StatsPeriod::Month, CarbonImmutable::now());

            if (! $report['hasHistory'] || ($report['minutes'] === 0 && $report['finished'] === 0)) {
                $this->line("Skipped {$user->email}: nothing to report.");

                continue;
            }

            Mail::raw($this->body($report), fn ($message) => $message
                ->to($user->email)
                ->subject('Your GameHub month: '.Format::durationText($report['minutes']).' played'));

            $this->line("Sent to {$user->email}");
        }

        return self::SUCCESS;
    }

    private function body(array $report): string
    {
        $change = StatsService::change($report['minutes'], $report['previousMinutes']);

        $lines = [
            'Your last 30 days in GameHub',
            '',
            'Played: '.Format::durationText($report['minutes'])
                .($change !== null ? sprintf(' (%s%d%% on the 30 days before)', $change >= 0 ? '+' : '', $change) : ''),
            "Games played: {$report['gamesPlayed']}",
            "Finished: {$report['finished']}",
            "Days with play: {$report['activeDays']}",
            '',
        ];

        if ($report['top']) {
            $lines[] = 'Most played';

            foreach (array_slice($report['top'], 0, 5) as $row) {
                $lines[] = '  '.$row['entry']->game->title.' - '.Format::durationText($row['minutes']);
            }
        }

        return implode("\n", $lines)."\n";
    }
}
