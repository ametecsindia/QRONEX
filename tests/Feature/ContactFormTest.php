<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ContactFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_enquiry_keeps_topic_and_rejects_a_phone_number_as_team_size(): void
    {
        $this->get('/contact?topic=pricing')->assertOk()->assertSee('value="pricing"', false);

        $this->from('/contact')->post('/contact', ['name' => 'Ravi', 'email' => 'r@x.example', 'message' => 'Hi', 'employees' => 7633222121])
            ->assertRedirect('/contact')->assertSessionHasErrors(['employees' => 'That looks like a phone number. Enter how many employees you have, for example 40.']);
        $this->from('/contact')->followingRedirects()->post('/contact', ['name' => 'Ravi', 'email' => 'r@x.example', 'message' => 'Hi', 'employees' => 7633222121])
            ->assertSee('That looks like a phone number', false)->assertSee('aria-invalid="true"', false);   // shown inline under the field

        $this->post('/contact', ['topic' => 'demo', 'name' => 'Ravi', 'email' => 'r@x.example', 'message' => 'Demo please', 'employees' => 40, 'phone' => '+91 98480 12345'])
            ->assertSessionHas('ok');
        $this->assertSame('Website enquiry (A demo) · 40 employees', SupportTicket::latest('id')->value('subject'));
    }
}
