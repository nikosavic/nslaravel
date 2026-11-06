<?php

namespace App\Console\Commands;

use App\Jobs\SyncLinkedAccount;
use App\Models\LinkedAccount;
use App\Services\LibrarySyncService;
use Illuminate\Console\Command;

class SyncLibraries extends Command
{
    protected $signature = 'gamehub:sync
        {--account= : Only sync this linked account id}
        {--now : Run inline and print results instead of queueing}';

    protected $description = 'Sync game libraries for all linked accounts';

    public function handle(LibrarySyncService $sync): int
    {
        $accounts = LinkedAccount::query()
            ->when($this->option('account'), fn ($query, $id) => $query->whereKey($id))
            ->get();

        if ($accounts->isEmpty()) {
            $this->info('No linked accounts to sync.');

            return self::SUCCESS;
        }

        foreach ($accounts as $account) {
            if (! $this->option('now')) {
                SyncLinkedAccount::dispatch($account->id);
                $this->line("Queued #{$account->id} {$account->provider->label()} ({$account->display_name})");

                continue;
            }

            $run = $sync->sync($account);

            $this->line(sprintf(
                '#%d %s (%s): %s - %d seen, %d new, %d updated%s',
                $account->id,
                $account->provider->label(),
                $account->display_name,
                $run->status,
                $run->games_seen,
                $run->created_count,
                $run->updated_count,
                $run->error ? " - {$run->error}" : '',
            ));
        }

        return self::SUCCESS;
    }
}
