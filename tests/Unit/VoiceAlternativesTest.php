<?php

namespace Tests\Unit;

use App\Support\VoiceAlternatives;
use Tests\TestCase;

class VoiceAlternativesTest extends TestCase
{
    public function test_known_alternatives_choose_or_accept(): void
    {
        $this->assertSame('accept', VoiceAlternatives::decide(['buka buku saku', 'Buku Saku.'])['decision']);
        $choice = VoiceAlternatives::decide(['buku saku', 'ucapan']);
        $this->assertSame('choose', $choice['decision']);
        $this->assertSame('clarify', $choice['type']);
        $this->assertSame('none', VoiceAlternatives::decide(['kamus'])['decision']);
    }
}
