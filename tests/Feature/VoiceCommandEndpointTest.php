<?php

namespace Tests\Feature;

use Tests\TestCase;

class VoiceCommandEndpointTest extends TestCase
{
    public function test_public_endpoint_limits_input_and_routes(): void
    {
        $this->postJson(route('voice.command'), ['transcript' => 'buka buku saku'])
            ->assertOk()->assertExactJson(['type' => 'navigate', 'target' => 'buku-saku', 'index' => null]);
        $this->postJson(route('voice.command'), ['transcript' => 'buka kamus'])
            ->assertOk()->assertJsonPath('type', 'unknown');
        $this->postJson(route('voice.command'), ['transcript' => str_repeat('a', 201)])->assertUnprocessable();
        $this->postJson(route('voice.command'), ['transcript' => 'a', 'alternatives' => ['a', 'b', 'c', 'd']])->assertUnprocessable();
    }

    public function test_endpoint_is_throttled_per_ip(): void
    {
        for ($i = 0; $i < 60; $i++) {
            $this->postJson(route('voice.command'), ['transcript' => 'ya'])->assertOk();
        }
        $this->postJson(route('voice.command'), ['transcript' => 'ya'])->assertStatus(429);
    }
}
