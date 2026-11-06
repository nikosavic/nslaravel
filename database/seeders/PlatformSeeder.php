<?php

namespace Database\Seeders;

use App\Models\Platform;
use Illuminate\Database\Seeder;

class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        $platforms = [
            ['steam', 'Steam', 'pc'],
            ['epic', 'Epic Games Store', 'pc'],
            ['gog', 'GOG', 'pc'],
            ['pc-other', 'PC (other)', 'pc'],
            ['ps5', 'PlayStation 5', 'playstation'],
            ['ps4', 'PlayStation 4', 'playstation'],
            ['xbox-series', 'Xbox Series X|S', 'xbox'],
            ['xbox-one', 'Xbox One', 'xbox'],
            ['switch', 'Nintendo Switch', 'nintendo'],
        ];

        foreach ($platforms as [$slug, $name, $family]) {
            Platform::updateOrCreate(['slug' => $slug], ['name' => $name, 'family' => $family]);
        }
    }
}
