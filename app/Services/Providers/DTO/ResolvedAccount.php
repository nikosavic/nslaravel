<?php

namespace App\Services\Providers\DTO;

final readonly class ResolvedAccount
{
    /**
     * @param  array<string, mixed>|null  $credentials  Tokens to store (encrypted) on the linked account; null for providers that need none.
     */
    public function __construct(
        public string $externalId,
        public string $displayName,
        public ?string $warning = null,
        public ?array $credentials = null,
    ) {
    }
}
