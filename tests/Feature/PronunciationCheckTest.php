<?php

namespace Tests\Feature;

use App\Contracts\PronunciationChecker;
use App\Livewire\PronunciationCheck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\TestCase;

class PronunciationCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('ucapan:127.0.0.1');
        DB::table('english_vocab')->insert(['external_id' => 1, 'word' => 'hello', 'pos' => 'int.', 'definition' => 'greeting', 'cefr_level' => 'A1', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('mandarin_words')->insert(['hanzi' => '可以', 'pinyin' => 'kě yǐ', 'meaning_id' => 'boleh', 'meaning_en' => 'can', 'batch_number' => 1, 'order_in_batch' => 1, 'created_at' => now(), 'updated_at' => now()]);
    }

    private function wav(float $seconds = 1): UploadedFile
    {
        $size = (int) (8000 * $seconds);
        $header = 'RIFF'.pack('V', 36 + $size).'WAVEfmt '.pack('VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8).'data'.pack('V', $size);

        return UploadedFile::fake()->createWithContent('recording.wav', $header.str_repeat("\x80", $size));
    }

    private function checker(): void
    {
        $this->app->instance(PronunciationChecker::class, new class implements PronunciationChecker
        {
            public function check(string $audioPath, string $targetText, string $lang, ?string $sttTranscript = null, ?string $targetPinyin = null): array
            {
                return ['isCorrect' => $sttTranscript === $targetText, 'feedback' => 'Coba lagi'];
            }
        });
    }

    public function test_public_query_and_dictionary_selection(): void
    {
        $this->get('/pronunciation-check?lang=mandarin&word=Family')->assertOk()->assertSee('&quot;query&quot;:&quot;Family&quot;', false);
        Livewire::test(PronunciationCheck::class)->set('query', 'hello')->assertSee('hello');
        Livewire::test(PronunciationCheck::class)->call('selectWord', 'fake', null, null, null, 'en-US')->assertSet('selectedWord', null);
    }

    public function test_valid_audio_uses_checker_and_fake_selection_is_rejected(): void
    {
        $this->checker();
        Livewire::test(PronunciationCheck::class)->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', $this->wav())->call('checkPronunciation', 'hello')->assertSet('result.isCorrect', true);
        $this->expectException(CannotUpdateLockedPropertyException::class);
        Livewire::test(PronunciationCheck::class)->set('selectedWord', ['headword' => 'fake']);
    }

    public function test_invalid_language_transcript_audio_size_type_and_duration_are_rejected(): void
    {
        $this->checker();
        $base = fn () => Livewire::test(PronunciationCheck::class)->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US');
        $base()->set('lang', 'bogus')->set('audioFile', $this->wav())->call('checkPronunciation', 'hello')->assertSet('result', null);
        $base()->set('audioFile', $this->wav())->call('checkPronunciation', str_repeat('a', 501))->assertSet('result', null);
        $base()->set('audioFile', UploadedFile::fake()->createWithContent('a.txt', 'bad'))->call('checkPronunciation', 'hello')->assertSet('result', null);
        $base()->set('audioFile', $this->wav(0.1))->call('checkPronunciation', 'hello')->assertSet('result', null);
        $base()->set('audioFile', UploadedFile::fake()->create('huge.wav', 3000))->call('checkPronunciation', 'hello')->assertSet('result', null);
    }

    public function test_rate_limit_is_per_ip(): void
    {
        $this->checker();
        for ($i = 0; $i < 10; $i++) {
            RateLimiter::hit('ucapan:127.0.0.1', 60);
        }
        Livewire::test(PronunciationCheck::class)->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', $this->wav())->call('checkPronunciation', 'hello')
            ->assertSet('errorMessage', 'Terlalu banyak pemeriksaan. Coba lagi sebentar.');
    }
}
