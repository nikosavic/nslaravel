<?php

namespace Tests\Feature;

use App\Models\LibraryEntry;
use Tests\TestCase;

class LibraryEntryTest extends TestCase
{
    public function test_playtime_is_split_into_a_number_and_a_unit(): void
    {
        $parts = fn (int $minutes) => (new LibraryEntry(['playtime_minutes' => $minutes]))->playtimeParts();

        $this->assertNull($parts(0));
        $this->assertSame(['1', 'minute'], $parts(1));
        $this->assertSame(['2', 'minutes'], $parts(2));
        $this->assertSame(['59', 'minutes'], $parts(59));
        $this->assertSame(['1', 'hour'], $parts(60));
        $this->assertSame(['3.7', 'hours'], $parts(221));
        $this->assertSame(['64', 'hours'], $parts(3839));
        $this->assertSame(['1,200', 'hours'], $parts(72000));
    }
}
