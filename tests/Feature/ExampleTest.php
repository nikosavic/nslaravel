<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_root_url_sends_visitors_to_their_language(): void
    {
        $this->get('/')->assertRedirect('/en');
    }
}
