<?php

use App\Services\Providers\PlaystationProvider;
use App\Services\Providers\SteamProvider;
use App\Services\Providers\XboxProvider;

return [

    // When a sync sees new playtime on an Unsorted/Backlog/Wishlist/On hold game, flip it to Playing.
    'auto_promote' => env('GAMEHUB_AUTO_PROMOTE', true),

    // provider type => adapter class. A broken adapter never affects the others.
    'providers' => [
        'steam' => SteamProvider::class,
        'xbox' => XboxProvider::class,
        'playstation' => PlaystationProvider::class,
    ],

    'steam' => [
        // https://steamcommunity.com/dev/apikey
        'key' => env('STEAM_API_KEY'),
        'cdn' => env('STEAM_CDN', 'https://cdn.cloudflare.steamstatic.com'),
    ],

    'playstation' => [
        // The public OAuth client of the PlayStation mobile app, shared by community libraries such as psn-api.
        // Sony can change or revoke it; if logins start failing for everyone, override both values in .env.
        'client_id' => env('PSN_CLIENT_ID', '09515159-7237-4370-9b40-3806e67c0891'),
        'client_auth' => env('PSN_CLIENT_AUTH', 'MDk1MTUxNTktNzIzNy00MzcwLTliNDAtMzgwNmU2N2MwODkxOnVjUGprYTV0bnRCMktxc1A='),
    ],

    'xbox' => [
        // OpenXBL, a third-party Xbox Live gateway (https://xbl.io), is the alternative: each user brings their own key.
        // Direct Microsoft login: the Application (client) ID of a free Azure app registration (public client).
        'client_id' => env('XBOX_CLIENT_ID'),
        'base_url' => env('XBOX_BASE_URL', 'https://api.xbl.io'),
        'titles_path' => env('XBOX_TITLES_PATH', '/v2/achievements'),
    ],

    'igdb' => [
        // Twitch developer console application (client credentials flow).
        'client_id' => env('IGDB_CLIENT_ID'),
        'client_secret' => env('IGDB_CLIENT_SECRET'),
    ],

];
