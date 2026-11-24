<?php

namespace Tests\Unit;

use App\Support\VoiceCommandParser;
use Tests\TestCase;

class VoiceCommandParserTolerantTest extends TestCase
{
    public function test_normalization_and_one_word_misrecognition(): void
    {
        $this->assertSame('buku saku', VoiceCommandParser::normalize('Buku, Saku!'));
        $this->assertSame('buku-saku', VoiceCommandParser::parse('buka buku saku dong')['target']);
        $this->assertSame('conversation', VoiceCommandParser::parse('ngobroll')['target']);
        $this->assertSame('unknown', VoiceCommandParser::parse('ngobrol dan ucapan')['type']);
    }
}
