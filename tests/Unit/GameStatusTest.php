<?php

namespace Tests\Unit;

use App\Enums\GameStatus;
use PHPUnit\Framework\TestCase;

class GameStatusTest extends TestCase
{
    public function test_every_status_has_its_own_hex_colour(): void
    {
        $colors = array_map(fn (GameStatus $s) => $s->color(), GameStatus::cases());

        foreach ($colors as $color) {
            $this->assertMatchesRegularExpression('/^#[0-9a-f]{6}$/', $color);
        }

        $this->assertCount(count($colors), array_unique($colors), 'two statuses share a colour');
    }
}
