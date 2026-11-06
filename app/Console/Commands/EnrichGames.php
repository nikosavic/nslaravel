<?php

namespace App\Console\Commands;

use App\Jobs\EnrichGame;
use App\Models\Game;
use App\Services\Igdb\IgdbClient;
use Illuminate\Console\Command;

class EnrichGames extends Command
{
    protected $signature = 'gamehub:enrich {--retry : Also retry games IGDB could not match before}';

    protected $description = 'Queue IGDB lookups for games that have not been enriched yet (run after adding IGDB credentials)';

    public function handle(IgdbClient $igdb): int
    {
        if (! $igdb->isConfigured()) {
            $this->error('Set IGDB_CLIENT_ID and IGDB_CLIENT_SECRET in .env first.');

            return self::FAILURE;
        }

        if ($this->option('retry')) {
            Game::whereNull('igdb_id')->update(['enriched_at' => null]);
        }

        $count = 0;

        Game::whereNull('enriched_at')->select('id')->chunkById(200, function ($games) use (&$count) {
            foreach ($games as $game) {
                EnrichGame::dispatch($game->id);
                $count++;
            }
        });

        $this->info("Queued {$count} game(s) for enrichment.");

        return self::SUCCESS;
    }
}
