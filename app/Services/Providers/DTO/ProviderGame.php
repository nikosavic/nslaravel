<?php

namespace App\Services\Providers\DTO;

use Carbon\CarbonImmutable;

/** One owned game as reported by a provider, before it is matched to a canonical Game. */
final readonly class ProviderGame
{
    public function __construct(
        public string $externalId,
        public string $title,
        public string $platformSlug,
        public int $playtimeMinutes = 0,
        public ?CarbonImmutable $lastPlayedAt = null,
        public ?string $coverUrl = null,
    ) {
    }
}
