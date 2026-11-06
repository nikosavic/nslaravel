<?php

namespace App\Services\Providers\Contracts;

use App\Enums\ProviderType;
use App\Models\LinkedAccount;
use App\Services\Providers\DTO\ProviderGame;
use App\Services\Providers\DTO\ResolvedAccount;
use App\Services\Providers\Exceptions\ProviderException;

interface LibraryProvider
{
    public function type(): ProviderType;

    /**
     * Turn whatever the user typed (id, URL, handle) into a stable account id plus display name.
     *
     * @throws ProviderException
     */
    public function resolveAccount(string $input): ResolvedAccount;

    /**
     * Stream every game the account owns or has played.
     *
     * @return iterable<ProviderGame>
     *
     * @throws ProviderException
     */
    public function fetchLibrary(LinkedAccount $account): iterable;
}
