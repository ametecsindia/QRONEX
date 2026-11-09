<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThrottlePerRouteTest extends TestCase
{
    use RefreshDatabase;

    public function test_traffic_on_one_route_does_not_use_up_another_routes_limit(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->get('/health');
        }

        $this->post('/register')->assertStatus(302); // validation redirect, not 429
    }

    public function test_a_route_still_hits_its_own_limit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post('/register')->assertStatus(302);
        }

        $this->post('/register')->assertStatus(429);
    }
}
