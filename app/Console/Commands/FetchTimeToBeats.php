<?php

namespace App\Console\Commands;

use App\Jobs\FetchTimeToBeat;
use App\Models\Game;
use App\Services\Igdb\IgdbClient;
use Illuminate\Console\Command;

class FetchTimeToBeats extends Command
{
    protected $signature = 'gamehub:time-to-beat {--retry : Ask again for games IGDB had no time for}';

    protected $description = 'Queue IGDB time-to-beat lookups for matched games that have not been checked yet';

    public function handle(IgdbClient $igdb): int
    {
        if (! $igdb->isConfigured()) {
            $this->error('Set IGDB_CLIENT_ID and IGDB_CLIENT_SECRET in .env first.');

            return self::FAILURE;
        }

        if ($this->option('retry')) {
            Game::whereNull('ttb_main_minutes')->whereNull('ttb_complete_minutes')->update(['ttb_checked_at' => null]);
        }

        $count = 0;

        Game::whereNotNull('igdb_id')->whereNull('ttb_checked_at')->select('id')->chunkById(200, function ($games) use (&$count) {
            foreach ($games as $game) {
                FetchTimeToBeat::dispatch($game->id);
                $count++;
            }
        });

        $this->info("Queued {$count} game(s).");

        return self::SUCCESS;
    }
}
