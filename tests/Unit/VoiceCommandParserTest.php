<?php

namespace Tests\Unit;

use App\Support\VoiceCommandParser;
use Tests\TestCase;

class VoiceCommandParserTest extends TestCase
{
    public function test_only_active_pages_are_navigable(): void
    {
        foreach (['beranda' => 'home', 'ngobrol' => 'conversation', 'ucapan' => 'pronunciation-check', 'buku saku' => 'buku-saku', 'bantuan' => 'bantuan'] as $phrase => $route) {
            $this->assertSame(['type' => 'navigate', 'target' => $route, 'index' => null], VoiceCommandParser::parse('buka '.$phrase));
        }
        foreach (['kamus', 'ujian', 'game', 'kartu', 'kamera', 'dashboard'] as $phrase) {
            $this->assertNotSame('navigate', VoiceCommandParser::parse('buka '.$phrase)['type']);
        }
    }
}
