<?php

namespace Tests\Unit;

use App\Services\SttMatchPronunciationChecker;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class SttMatchPronunciationCheckerTest extends TestCase
{
    private SttMatchPronunciationChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = new SttMatchPronunciationChecker;
    }

    public function test_matches_when_transcript_is_close_to_target(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'Hello, how are you?', 'english', 'hello how are you');

        $this->assertTrue($result['isCorrect']);
    }

    public function test_does_not_match_when_transcript_is_far_from_target(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'Hello, how are you?', 'english', 'goodbye see you later');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_is_incorrect_when_nothing_was_heard(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'Hello', 'english', '');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_matches_mandarin_characters_exactly(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '你好', 'mandarin', '你好');

        $this->assertTrue($result['isCorrect']);
    }

    public function test_a_single_wrong_character_fails_even_if_mostly_similar(): void
    {
        // similar_text alone would score "你好" vs "你号" as ~50% similar (still
        // "close"); Mandarin correctness requires an exact character match since
        // each Hanzi carries its own meaning/tone.
        $result = $this->checker->check('/tmp/audio.wav', '你好', 'mandarin', '你号');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_multi_character_mismatch_points_at_the_exact_character(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '你好', 'mandarin', '你号', 'nǐ hǎo');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('karakter ke-2', $result['feedback']);
        $this->assertStringContainsString('好', $result['feedback']);
        $this->assertStringContainsString('号', $result['feedback']);
        $this->assertStringContainsString('nǐ hǎo', $result['feedback']);
    }

    public function test_single_character_mismatch_does_not_use_the_per_character_wording(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '妈', 'mandarin', '马');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringNotContainsString('karakter ke-', $result['feedback']);
    }

    public function test_punctuation_only_transcript_is_reported_as_an_engine_limitation_not_a_wrong_answer(): void
    {
        // Chrome's zh-CN engine sometimes returns "——" for short, flat-tone
        // syllables — that's the STT engine failing to transcribe anything,
        // not the user mispronouncing the word, so it must not get the
        // "Kurang tepat" wording that implies the user's answer was wrong.
        $result = $this->checker->check('/tmp/audio.wav', '一', 'mandarin', '——');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringNotContainsString('Kurang tepat', $result['feedback']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $result['feedback']);
    }

    public function test_digit_only_transcript_is_also_reported_as_an_engine_limitation(): void
    {
        // Chrome's zh-CN engine has also been observed misreading the short
        // syllable "一" (yī) as the digit "1111" instead of Hanzi — still not
        // a real transcript of what was said, just a different failure shape.
        $result = $this->checker->check('/tmp/audio.wav', '一', 'mandarin', '1111');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringNotContainsString('Kurang tepat', $result['feedback']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $result['feedback']);
        // The raw transcript is named so the user can see what the engine produced.
        $this->assertStringContainsString('yang tertangkap: "1111"', $result['feedback']);
    }

    public function test_punctuation_only_transcript_is_detected_for_english_too(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'a', 'english', '...');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $result['feedback']);
    }

    /**
     * Chrome's zh-CN recognizer almost always appends punctuation ("妈妈。"),
     * and may put spaces between characters; neither is a pronunciation error.
     *
     * @return array<string, array{string, string}>
     */
    public static function mandarinPunctuationAndSpaceCases(): array
    {
        return [
            'trailing full stop, two chars' => ['妈妈', '妈妈。'],
            'trailing full stop, one char' => ['我', '我。'],
            'trailing exclamation' => ['你好', '你好！'],
            'ascii space between chars' => ['你好', '你 好'],
            'full-width space between chars' => ['你好', '你　好'],
            'punctuation in the target too' => ['你好！', '你好'],
        ];
    }

    #[DataProvider('mandarinPunctuationAndSpaceCases')]
    public function test_mandarin_ignores_punctuation_and_spaces(string $target, string $heard): void
    {
        $result = $this->checker->check('/tmp/audio.wav', $target, 'mandarin', $heard);

        $this->assertTrue($result['isCorrect'], $result['feedback']);
    }

    public function test_mandarin_different_characters_still_fail_after_normalization(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', '爸爸', 'mandarin', '妈妈。');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('karakter ke-1 beda (target "爸", terdengar "妈")', $result['feedback']);
        $this->assertStringContainsString('karakter ke-2 beda (target "爸", terdengar "妈")', $result['feedback']);
        $this->assertStringNotContainsString('。', mb_substr($result['feedback'], mb_strpos($result['feedback'], '—')));
    }

    public function test_mandarin_diff_positions_are_counted_after_normalization(): void
    {
        // Without normalization the space would shift "们" to position 3.
        $result = $this->checker->check('/tmp/audio.wav', '我们', 'mandarin', '我 门。');

        $this->assertFalse($result['isCorrect']);
        $this->assertStringContainsString('karakter ke-2 beda (target "们", terdengar "门")', $result['feedback']);
        $this->assertStringNotContainsString('karakter ke-1', $result['feedback']);
        $this->assertStringNotContainsString('karakter ke-3', $result['feedback']);
    }

    public function test_full_stop_only_or_empty_transcript_is_still_not_a_wrong_answer(): void
    {
        $fullStop = $this->checker->check('/tmp/audio.wav', '妈妈', 'mandarin', '。');
        $this->assertFalse($fullStop['isCorrect']);
        $this->assertStringContainsString('Mesin pengenalan suara kesulitan', $fullStop['feedback']);
        $this->assertStringNotContainsString('Kurang tepat', $fullStop['feedback']);

        $empty = $this->checker->check('/tmp/audio.wav', '妈妈', 'mandarin', '');
        $this->assertFalse($empty['isCorrect']);
        $this->assertStringContainsString('Tidak ada suara yang terdeteksi', $empty['feedback']);
    }

    public function test_mandarin_ignores_case_of_latin_letters(): void
    {
        foreach ([['T恤', 't恤。'], ['卡拉OK', '卡拉ok'], ['卡拉ok', '卡拉 O K！']] as [$target, $heard]) {
            $result = $this->checker->check('/tmp/audio.wav', $target, 'mandarin', $heard);

            $this->assertTrue($result['isCorrect'], "{$target} / {$heard}: {$result['feedback']}");
        }
    }

    public function test_english_ignores_punctuation_spaces_and_case_in_phrases(): void
    {
        foreach (['Thank you!', 'THANK YOU', 'thank-you', 'thankyou', '  Thank,  you. '] as $heard) {
            $result = $this->checker->check('/tmp/audio.wav', 'thank you', 'english', $heard);

            $this->assertTrue($result['isCorrect'], "{$heard}: {$result['feedback']}");
            $this->assertStringContainsString('Bagus!', $result['feedback']);
        }
    }

    public function test_english_different_word_still_fails_after_normalization(): void
    {
        $result = $this->checker->check('/tmp/audio.wav', 'mother', 'english', 'Weather.');

        $this->assertFalse($result['isCorrect']);
    }

    public function test_english_single_word_with_capital_and_full_stop_matches(): void
    {
        foreach (['Mother.', 'Mother,', 'mother.'] as $heard) {
            $result = $this->checker->check('/tmp/audio.wav', 'mother', 'english', $heard);

            $this->assertTrue($result['isCorrect'], "{$heard}: {$result['feedback']}");
        }
    }
}
