<?php

namespace App\Services\Providers;

use App\Enums\ProviderType;
use App\Services\Providers\Contracts\LibraryProvider;
use App\Services\Providers\Exceptions\ProviderException;
use Illuminate\Contracts\Container\Container;

class ProviderRegistry
{
    public function __construct(private Container $container)
    {
    }

    public function for(ProviderType $type): LibraryProvider
    {
        $class = config("gamehub.providers.{$type->value}");

        if (! $class) {
            throw new ProviderException("No adapter configured for {$type->label()}.");
        }

        return $this->container->make($class);
    }
}
