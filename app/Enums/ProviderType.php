<?php

namespace App\Enums;

enum ProviderType: string
{
    case Steam = 'steam';
    case Xbox = 'xbox';
    case Playstation = 'playstation';

    public function label(): string
    {
        return match ($this) {
            self::Steam => 'Steam',
            self::Xbox => 'Xbox',
            self::Playstation => 'PlayStation',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Steam => '#66c0f4',
            self::Xbox => '#3ddc84',
            self::Playstation => '#3d7bff',
        };
    }

    /** Platform slug used when the provider cannot tell which console generation a title runs on. */
    public function defaultPlatformSlug(): string
    {
        return match ($this) {
            self::Steam => 'steam',
            self::Xbox => 'xbox-series',
            self::Playstation => 'ps5',
        };
    }
}
