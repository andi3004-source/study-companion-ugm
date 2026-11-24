<?php

namespace Tests\Feature;

use Tests\TestCase;

class BerandaTest extends TestCase
{
    public function test_home_has_exactly_three_feature_cards_without_sidebar(): void
    {
        $html = $this->get('/')->assertOk()->getContent();
        $this->assertSame(3, substr_count($html, 'class="kartu-fitur block'));
        $this->assertStringContainsString(route('conversation'), $html);
        $this->assertStringContainsString(route('pronunciation-check'), $html);
        $this->assertStringContainsString(route('buku-saku'), $html);
        $this->assertStringNotContainsString('aria-label="Navigasi utama"', $html);
    }
}
