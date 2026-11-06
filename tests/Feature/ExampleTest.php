<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_guests_see_the_website_and_portal_needs_login(): void
    {
        $this->get('/stormfinancial')->assertRedirect('/login');
    }
}
