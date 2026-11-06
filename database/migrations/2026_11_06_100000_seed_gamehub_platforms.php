<?php

use Database\Seeders\PlatformSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * GameHub needs its platform list (Steam, PlayStation, Xbox...) to exist. Seeding it from a migration means
 * a normal deploy (`php artisan migrate --force`) is enough; no separate `db:seed` step on the server.
 * PlatformSeeder is idempotent, so running it twice is harmless.
 */
return new class extends Migration
{
    public function up(): void
    {
        (new PlatformSeeder)->run();
    }

    public function down(): void
    {
        // Nothing to undo: the platforms table is dropped by its own migration.
    }
};
