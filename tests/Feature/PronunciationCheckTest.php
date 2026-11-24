<?php

namespace Tests\Feature;

use App\Contracts\PronunciationChecker;
use App\Livewire\PronunciationCheck;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class PronunciationCheckTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(): User
    {
        $this->seed(ReferenceDataSeeder::class);

        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    /**
     * ReferenceDataSeeder doesn't touch english_vocab/mandarin_hsk_words —
     * those come from EnglishVocabSeeder/MandarinHskSeeder, which import
     * the full ~15.8k-row dictionaries and are too slow to run per-test.
     * KamusSearchService::results() only needs the columns it selects, so a
     * couple of minimal rows are enough to test the suggestions dropdown.
     */
    protected function seedDictionaryFixture(): void
    {
        DB::table('english_vocab')->insert([
            'external_id' => 1,
            'word' => 'hello',
            'pos' => 'int.',
            'definition' => 'greeting',
            'cefr_level' => 'A1',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function test_typing_a_query_shows_dictionary_suggestions_not_free_text_submission(): void
    {
        $user = $this->onboardedUser();
        $this->seedDictionaryFixture();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'english')
            ->set('query', 'hello')
            ->assertSee('hello')
            ->assertSet('selectedWord', null); // typing alone never sets a target — must pick from the list
    }

    public function test_selecting_a_word_shows_hanzi_pinyin_and_meanings_before_recording(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'mandarin')
            ->set('query', '可以')
            ->call('selectWord', '可以', 'kě yǐ', null, 'can; may; possible; able to; not bad; pretty good', 'zh-CN')
            ->assertSet('selectedWord.headword', '可以')
            ->assertSet('audioFile', null)
            ->assertSee('可以')
            ->assertSee('kě yǐ')
            ->assertSee('can; may; possible');
    }

    public function test_checking_pronunciation_uses_the_ml_service_when_it_is_up(): void
    {
        Http::fake([
            '*/check/english' => Http::response([
                'is_correct' => true,
                'score' => 95.0,
                'heard_phonemes' => 'h ə l oʊ',
                'target_phonemes' => 'h ə l oʊ',
                'mispronounced_words' => [],
            ], 200),
        ]);

        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'english')
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', 'hello')
            ->assertSet('result.isCorrect', true);
    }

    public function test_checking_pronunciation_still_returns_a_result_when_the_ml_service_is_down(): void
    {
        Http::fake([
            '*/check/english' => Http::response([], 500),
        ]);

        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'english')
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', 'hello')
            ->assertSet('result.isCorrect', true) // falls back to STT-match: transcript matches target
            ->assertSet('errorMessage', null);
    }

    public function test_multi_character_mandarin_mismatch_points_at_the_wrong_character(): void
    {
        // Multi-character Mandarin always goes through the fallback (see
        // MlPronunciationChecker's guard), regardless of the 'ml' driver, so
        // no Http::fake() is needed here.
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'mandarin')
            ->call('selectWord', '可以', 'kě yǐ', null, 'can; may', 'zh-CN')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', '可己')
            ->assertSet('result.isCorrect', false)
            ->assertSee('karakter ke-2');
    }

    public function test_checking_pronunciation_requires_a_selected_word_and_a_recording(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->call('checkPronunciation')
            ->assertSet('errorMessage', 'Pilih kata dari daftar dulu.');

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->call('checkPronunciation')
            ->assertSet('errorMessage', 'Rekam suara dulu sebelum cek pengucapan.');
    }

    public function test_the_stt_transcript_is_kept_alongside_the_verdict(): void
    {
        Http::fake(['*/check/english' => Http::response([], 500)]);

        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'english')
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', 'hello there')
            ->assertSet('transcript', 'hello there')
            ->assertSee('hello there')
            ->assertSee('Coba lagi');
    }

    public function test_reset_check_clears_the_result_without_losing_the_selected_word(): void
    {
        Http::fake(['*/check/english' => Http::response([], 500)]);

        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'english')
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', 'hello')
            ->assertSet('result.isCorrect', true)
            ->call('resetCheck')
            ->assertSet('result', null)
            ->assertSet('transcript', null)
            ->assertSet('selectedWord.headword', 'hello');
    }

    public function test_clear_selection_lets_the_user_pick_a_different_word(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->call('clearSelection')
            ->assertSet('selectedWord', null);
    }

    public function test_the_write_practice_button_only_shows_for_mandarin_results(): void
    {
        Http::fake(['*/check/english' => Http::response([], 500)]);

        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->set('lang', 'english')
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', 'hello')
            // Not "Latihan Tulis" alone: the (always-present, Alpine-hidden)
            // writing-practice modal's title also contains that text in its
            // x-text attribute — the emoji only appears on the actual button.
            ->assertDontSee('✍️');
    }

    public function test_an_exception_from_the_checker_surfaces_as_an_error_instead_of_hanging(): void
    {
        $this->swap(
            PronunciationChecker::class,
            new class implements PronunciationChecker
            {
                public function check(string $audioPath, string $targetText, string $lang, ?string $sttTranscript = null, ?string $targetPinyin = null): array
                {
                    throw new \RuntimeException('boom');
                }
            }
        );

        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(PronunciationCheck::class)
            ->call('selectWord', 'hello', 'int.', null, 'greeting', 'en-US')
            ->set('audioFile', UploadedFile::fake()->create('recording.webm', 10))
            ->call('checkPronunciation', 'hello')
            ->assertSet('result', null)
            ->assertSet('errorMessage', 'Terjadi kesalahan saat memverifikasi pengucapan. Coba lagi.');
    }

    public function test_lang_and_word_query_params_prefill_the_search_box(): void
    {
        $user = $this->onboardedUser();

        // A real HTTP request, not Livewire::test() (which boots the
        // component through its own simulated request, not this test's
        // request()) — mount() reads request()->query() directly, so this
        // is the only way to actually exercise that path end to end.
        $response = $this->actingAs($user)->get('/pronunciation-check?lang=mandarin&word=Family');

        $response->assertOk();
        // Livewire hydrates the <input>'s value via its embedded snapshot
        // JSON, not a static value="..." HTML attribute, so assert against
        // that snapshot rather than the raw <input> markup.
        $response->assertSee('&quot;lang&quot;:&quot;mandarin&quot;', false);
        $response->assertSee('&quot;query&quot;:&quot;Family&quot;', false);
    }
}
