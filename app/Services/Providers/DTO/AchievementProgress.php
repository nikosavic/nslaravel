<?php

namespace App\Services\Providers\DTO;

/**
 * How far an account is through one game's achievements or trophies. Steam identifies the game by its appid
 * ($externalId); PlayStation only gives a title, so it is matched by name ($title, $platformSlug when known).
 */
final readonly class AchievementProgress
{
    /** @param array{bronze:int,silver:int,gold:int,platinum:int}|null $trophies earned per tier (PlayStation only) */
    public function __construct(
        public int $unlocked,
        public int $total,
        public ?string $externalId = null,
        public ?string $title = null,
        public ?string $platformSlug = null,
        public ?array $trophies = null,
    ) {
    }
}
