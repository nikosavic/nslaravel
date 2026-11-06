<?php

namespace App\Services\Providers\Contracts;

use App\Models\LinkedAccount;
use App\Services\Providers\DTO\AchievementProgress;
use App\Services\Providers\Exceptions\ProviderException;

/** A provider that can also report achievement / trophy progress. Optional: a provider without it is skipped. */
interface ProvidesAchievements
{
    /**
     * @return iterable<AchievementProgress>
     *
     * @throws ProviderException
     */
    public function fetchAchievements(LinkedAccount $account): iterable;
}
