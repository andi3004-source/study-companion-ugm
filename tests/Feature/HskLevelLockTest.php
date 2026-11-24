<?php

namespace Tests\Feature;

use App\Contracts\AiClient;
use App\Livewire\Conversation\Chat;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\MandarinWord;
use App\Models\User;
use App\Services\ConversationService;
use App\Support\HskVocabularyPrompt;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class HskLevelLockTest extends TestCase
{
    use RefreshDatabase;

    /** Captures the system prompt; replies in the Mandarin + ###TERJEMAHAN_ID### format. */
    private function fakeClient(): AiClient
    {
        $client = new class implements AiClient
        {
            public ?string $system = null;

            public function isConfigured(): bool
            {
                return true;
            }

            public function sendMessage(string $model, string $system, array $messages, int $maxTokens = 200): array
            {
                $this->system = $system;

                return ['text' => "你好！你喜欢喝茶吗？\n###TERJEMAHAN_ID###\nHalo! Kamu suka minum teh?", 'tokens_in' => 1, 'tokens_out' => 1];
            }
        };

        $this->app->instance(AiClient::class, $client);

        return $client;
    }

    private function chatSession(string $lang): ConversationSession
    {
        $user = User::factory()->create(['start_date' => now()->toDateString(), 'track' => 'both', 'onboarded' => true]);

        $session = ConversationSession::create([
            'user_id' => $user->id,
            'lang' => $lang,
            'scenario_key' => $lang === 'mandarin' ? 'restaurant_ordering' : 'job_interview',
            'started_at' => now(),
        ]);
        session()->push('ngobrol.sesi', $session->id);

        return $session;
    }

    private function mandarinWord(string $hanzi, int $batch, int $order, int $level = 1): MandarinWord
    {
        return MandarinWord::create([
            'hanzi' => $hanzi, 'pinyin' => 'x', 'meaning_id' => 'x', 'meaning_en' => 'x',
            'batch_number' => $batch, 'order_in_batch' => $order, 'hsk_level_old' => $level,
        ]);
    }

    public function test_mandarin_session_gets_the_hsk1_list_the_lock_instruction_and_the_no_pinyin_rule(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $client = $this->fakeClient();
        $session = $this->chatSession('mandarin');

        $reply = app(ConversationService::class)->sendMessage($session, '你好');
        $system = $client->system;

        $this->assertStringContainsString('DAFTAR KOSAKATA HSK 1: ', $system);
        $this->assertContains('个', explode(' ', HskVocabularyPrompt::hsk1List()));
        $this->assertStringContainsString(HskVocabularyPrompt::hsk1List(), $system);
        $this->assertStringContainsString('HANYA boleh memakai kata dari DAFTAR KOSAKATA HSK 1', $system);
        $this->assertStringContainsString('TIDAK dianggap salah', $system);
        $this->assertStringContainsString('tanpa pinyin, tanpa tanda kurung, tanpa terjemahan', $system);

        // Existing instructions are untouched and the marker instruction stays last.
        $this->assertStringContainsString('sisipkan koreksi singkat', $system);
        $this->assertStringEndsWith('tulis HANYA terjemahan Indonesia dari balasanmu itu, tanpa tambahan penjelasan apapun.', $system);
        $this->assertLessThan(strpos($system, 'PENTING: Setelah balasanmu'), strpos($system, 'KUNCI LEVEL HSK 1'));

        // Marker parsing still works on a locked session.
        $this->assertSame('你好！你喜欢喝茶吗？', $reply->content);
        $this->assertSame('Halo! Kamu suka minum teh?', $reply->translation);
    }

    public function test_level_lock_off_leaves_the_mandarin_prompt_without_the_list(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        config(['hsk.level_lock' => false]);
        $client = $this->fakeClient();

        app(ConversationService::class)->sendMessage($this->chatSession('mandarin'), '你好');

        $this->assertStringNotContainsString('DAFTAR KOSAKATA HSK 1', $client->system);
        $this->assertStringNotContainsString('tanpa pinyin', $client->system);
        $this->assertStringContainsString('###TERJEMAHAN_ID###', $client->system);
    }

    public function test_english_session_never_gets_the_list(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $client = $this->fakeClient();

        app(ConversationService::class)->sendMessage($this->chatSession('english'), 'Hello');

        $this->assertStringNotContainsString('DAFTAR KOSAKATA HSK 1', $client->system);
        $this->assertStringNotContainsString('KUNCI LEVEL', $client->system);
    }

    public function test_list_is_cached_until_the_hsk1_data_changes(): void
    {
        $first = $this->mandarinWord('你', 1, 1);
        $this->mandarinWord('好', 1, 2);

        $this->assertSame('你 好', HskVocabularyPrompt::hsk1List());

        // A raw edit that doesn't touch updated_at/count/max id: the cached list is served.
        DB::table('mandarin_words')->where('id', $first->id)->update(['hanzi' => '我']);
        $this->assertSame('你 好', HskVocabularyPrompt::hsk1List());

        // A normal (Eloquent) update touches updated_at, which changes the cache key.
        $this->travel(1)->minutes();
        $first->fresh()->update(['meaning_id' => 'saya']);
        $this->assertSame('我 好', HskVocabularyPrompt::hsk1List());
    }

    public function test_duplicate_hanzi_appear_once_from_their_lowest_id_row(): void
    {
        // 我 first appears at batch 2 (lowest id), then again at batch 1;
        // 你 first appears at batch 1, then again at batch 3. Using the
        // lowest-id rows gives 你 (batch 1) before 我 (batch 2); using any
        // other row of 我 would put it first.
        $this->mandarinWord('我', 2, 1);
        $this->mandarinWord('你', 1, 2);
        $this->mandarinWord('我', 1, 1);
        $this->mandarinWord('你', 3, 1);
        $this->mandarinWord('学习', 1, 3, level: 2); // not HSK1

        $this->assertSame('你 我', HskVocabularyPrompt::hsk1List());
    }

    public function test_curriculum_seeded_twice_still_lists_each_hanzi_once_in_the_original_order(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $once = HskVocabularyPrompt::hsk1List();

        // Same 150 rows inserted again as ids 151-300 (a real database's double seed).
        MandarinWord::orderBy('id')->get()->each(fn (MandarinWord $w) => MandarinWord::create(
            $w->only(['hanzi', 'pinyin', 'pinyin_normalized', 'meaning_id', 'meaning_en', 'batch_number', 'order_in_batch', 'hsk_level_old'])
        ));
        $this->assertSame(300, MandarinWord::count());
        $this->assertSame(151, MandarinWord::where('hanzi', MandarinWord::find(1)->hanzi)->max('id'));

        $twice = HskVocabularyPrompt::hsk1List();
        $words = explode(' ', $twice);

        $this->assertSame($once, $twice);
        $this->assertCount(150, $words);
        $this->assertSame($words, array_values(array_unique($words)));
    }

    public function test_badge_shows_only_for_a_locked_mandarin_session(): void
    {
        $this->seed(ReferenceDataSeeder::class);

        $mandarin = $this->chatSession('mandarin');
        Livewire::actingAs($mandarin->user)->test(Chat::class, ['session' => $mandarin])
            ->assertSee('Level: HSK 1 (terkunci)');

        $english = $this->chatSession('english');
        Livewire::actingAs($english->user)->test(Chat::class, ['session' => $english])
            ->assertDontSee('Level: HSK 1 (terkunci)');

        config(['hsk.level_lock' => false]);
        Livewire::actingAs($mandarin->user)->test(Chat::class, ['session' => $mandarin])
            ->assertDontSee('Level: HSK 1 (terkunci)');
    }

    public function test_user_turn_keeps_its_input_mode_on_a_locked_session(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $this->fakeClient();
        $session = $this->chatSession('mandarin');

        app(ConversationService::class)->sendMessage($session, '你好', 'voice');

        $this->assertSame('voice', ConversationTurn::where('role', 'user')->value('input_mode'));
    }
}
