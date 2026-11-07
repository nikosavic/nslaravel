<?php

namespace App\Console\Commands;

use App\Enums\ProviderType;
use App\Models\LibraryEntry;
use App\Models\LinkedAccount;
use App\Services\AchievementSyncService;
use App\Services\Providers\Contracts\ProvidesAchievements;
use App\Services\Providers\ProviderRegistry;
use Illuminate\Console\Command;

class TrophyCheck extends Command
{
    protected $signature = 'gamehub:trophies
        {--sync : Also store the trophy counts after the check}';

    protected $description = 'Show which PlayStation games have no trophy data and which Sony trophy titles match no game';

    public function handle(ProviderRegistry $providers, AchievementSyncService $sync): int
    {
        $accounts = LinkedAccount::where('provider', ProviderType::Playstation->value)->get();

        if ($accounts->isEmpty()) {
            $this->warn('No PlayStation account is linked.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            $provider = $providers->for($account->provider);

            if (! $provider instanceof ProvidesAchievements) {
                continue;
            }

            $this->info("Account #{$account->id} ({$account->display_name})");

            $entries = LibraryEntry::with('game')->where('linked_account_id', $account->id)->get();
            $sonyTitles = [];

            foreach ($provider->fetchAchievements($account) as $progress) {
                $sonyTitles[] = (string) $progress->title;
            }

            $simple = fn (string $t) => AchievementSyncService::simplify($t);
            $gameKeys = $entries->map(fn ($e) => $simple($e->game->title))->all();
            $sonyKeys = array_map($simple, $sonyTitles);

            $unmatchedSony = array_values(array_filter($sonyTitles, fn ($t) => ! in_array($simple($t), $gameKeys, true)));
            $missing = $entries->filter(fn ($e) => ! in_array($simple($e->game->title), $sonyKeys, true) && $e->achievements_total === null);

            $this->line('Sony returned '.count($sonyTitles).' trophy titles.');
            $this->line('Trophy titles with no matching game in GameHub:');
            $this->line($unmatchedSony ? '  - '.implode("\n  - ", $unmatchedSony) : '  (none)');
            $this->line('Games in GameHub with no trophy data:');
            $this->line($missing->isNotEmpty() ? '  - '.$missing->map(fn ($e) => $e->game->title)->implode("\n  - ") : '  (none)');

            if ($this->option('sync')) {
                $this->info('Stored trophy counts on '.$sync->sync($account).' entries.');
            }
        }

        return self::SUCCESS;
    }
}
