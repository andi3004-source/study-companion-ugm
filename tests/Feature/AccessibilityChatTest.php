<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Chat;
use App\Models\ConversationSession;
use App\Models\ConversationTurn;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Keyboard / screen-reader markup on the study layout and the conversation
 * chat. These assert the HTML contract (labels, landmarks, live regions,
 * lang attributes); real screen-reader behaviour is checked manually.
 */
class AccessibilityChatTest extends TestCase
{
    use RefreshDatabase;

    protected function onboardedUser(): User
    {
        return User::factory()->create([
            'start_date' => now()->subDays(2)->toDateString(),
            'track' => 'both',
            'onboarded' => true,
        ]);
    }

    protected function mandarinSession(User $user, array $overrides = []): ConversationSession
    {
        $session = ConversationSession::create(array_merge([
            'user_id' => $user->id, 'lang' => 'mandarin', 'scenario_key' => 'restaurant_ordering', 'started_at' => now(),
        ], $overrides));
        session()->push('ngobrol.sesi', $session->id);

        return $session;
    }

    public function test_layout_has_lang_skip_links_landmarks_and_one_h1(): void
    {
        $user = $this->onboardedUser();
        $session = $this->mandarinSession($user);

        $html = $this->actingAs($user)->get(route('conversation.chat', $session))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('<html lang="id">', $html);
        $this->assertStringContainsString('<a href="#konten" class="skip-link">Lewati ke konten</a>', $html);
        $this->assertStringContainsString('<a href="#kolom-tulis" class="skip-link">Lewati ke kolom tulis</a>', $html);
        $this->assertLessThan(strpos($html, '<header'), strpos($html, 'Lewati ke kolom tulis'), 'Skip links must come before the header.');
        $this->assertStringContainsString('<header', $html);
        $this->assertStringNotContainsString('aria-label="Navigasi utama"', $html);
        $this->assertStringContainsString('<main id="konten"', $html);
        $this->assertStringContainsString('<footer', $html);
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_write_field_has_label_and_description(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Chat::class, ['session' => $this->mandarinSession($user)])
            ->assertSeeHtml('<label for="kolom-tulis"')
            ->assertSeeHtml('id="kolom-tulis"')
            ->assertSeeHtml('aria-describedby="petunjuk-kolom-tulis"')
            ->assertSeeHtml('id="petunjuk-kolom-tulis"')
            ->assertSee('Tulis jawabanmu dalam bahasa Mandarin atau pinyin dengan angka nada, misalnya wo3 bu4 you3 qian2. Tekan Enter untuk kirim, Shift+Enter untuk baris baru.')
            // Enter submits, but not with Shift or while an IME is composing.
            ->assertSeeHtml('$event.shiftKey || $event.isComposing || $event.keyCode === 229');
    }

    public function test_conversation_is_a_polite_log_with_named_messages_and_lang(): void
    {
        $user = $this->onboardedUser();
        $session = $this->mandarinSession($user);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => 'wo3 yao4 ka1fei1', 'created_at' => now()->subSeconds(3)]);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'user', 'content' => '我要咖啡', 'created_at' => now()->subSeconds(2)]);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => '好的，请稍等。', 'translation' => 'Baik, mohon tunggu.', 'created_at' => now()->subSecond()]);

        $html = Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->assertSeeHtml('role="log" aria-live="polite" aria-relevant="additions"')
            ->assertSee('Kamu:')
            ->assertSee('Teman bicara:')
            ->assertSeeHtml('<p lang="zh-Hans">好的，请稍等。</p>')
            ->assertSeeHtml('<span lang="id">Baik, mohon tunggu.</span>')
            ->assertSeeHtml('aria-label="Putar suara balasan 1"')
            ->html();

        $this->assertStringContainsString('<p lang="zh-Hans">我要咖啡</p>', $html);
        // Tone-numbered pinyin is not tagged as Chinese.
        $this->assertStringContainsString('<p >wo3 yao4 ka1fei1</p>', $html);
    }

    public function test_english_replies_are_tagged_en(): void
    {
        $user = $this->onboardedUser();
        $session = ConversationSession::create([
            'user_id' => $user->id, 'lang' => 'english', 'scenario_key' => 'job_interview', 'started_at' => now(),
        ]);
        session()->push('ngobrol.sesi', $session->id);
        ConversationTurn::create(['session_id' => $session->id, 'role' => 'assistant', 'content' => 'Tell me about yourself.']);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->assertSeeHtml('<p lang="en">Tell me about yourself.</p>');
    }

    public function test_mic_button_has_aria_pressed_and_finish_uses_inline_confirmation(): void
    {
        $user = $this->onboardedUser();

        Livewire::actingAs($user)->test(Chat::class, ['session' => $this->mandarinSession($user)])
            ->assertSeeHtml('aria-pressed="false" aria-label="Mulai bicara"')
            ->assertSeeHtml("state === 'listening' ? 'Berhenti' : 'Mulai bicara'")
            ->assertSee('Selesai dan analisis')
            ->assertSee('Selesaikan sesi ini dan analisis?')
            ->assertDontSeeHtml('wire:confirm');
    }

    public function test_status_region_announces_sent_and_analysis_states(): void
    {
        config(['services.gemini.key' => 'test-key']);
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [['content' => ['parts' => [['text' => '你好！']]]]],
                'usageMetadata' => ['promptTokenCount' => 1, 'candidatesTokenCount' => 1],
            ], 200),
        ]);
        $user = $this->onboardedUser();
        $session = $this->mandarinSession($user);

        Livewire::actingAs($user)->test(Chat::class, ['session' => $session])
            ->assertSeeHtml('role="status" id="status-percakapan"')
            ->assertSee('Teman sedang membalas')
            ->set('newMessage', 'ni3 hao3')
            ->call('sendMessage')
            ->assertSet('statusMessage', 'Terkirim')
            ->assertSeeHtml('<div role="status" id="status-percakapan" class="sr-only">');

        $pending = $this->mandarinSession($user, ['ended_at' => now()]);
        Livewire::actingAs($user)->test(Chat::class, ['session' => $pending])
            ->set('analysisQueuedAt', now()->timestamp)
            ->assertSee('Analisis sedang diproses');

        $done = $this->mandarinSession($user, [
            'ended_at' => now(),
            'analyzed_at' => now(),
            'error_analysis' => ['items' => [
                ['kategori' => 'nada', 'contoh' => 'ma3', 'koreksi' => 'ma1', 'penjelasan' => ''],
                ['kategori' => 'nada', 'contoh' => 'hao4', 'koreksi' => 'hao3', 'penjelasan' => ''],
            ]],
        ]);
        Livewire::actingAs($user)->test(Chat::class, ['session' => $done])
            ->assertSee('Analisis selesai, 2 kesalahan')
            // Ended session: no write field, so no skip link to it.
            ->assertDontSeeHtml('id="kolom-tulis"');
    }
}
