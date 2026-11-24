<?php

namespace Tests\Feature;

use App\Livewire\Conversation\Picker;
use App\Livewire\PronunciationCheck;
use Livewire\Livewire;
use Tests\TestCase;

class AccessibilityFixesTest extends TestCase
{
    public function test_pronunciation_controls_are_labelled_and_messages_live(): void
    {
        Livewire::test(PronunciationCheck::class)
            ->assertSeeHtml('<label for="ucapan-bahasa"')
            ->assertSeeHtml('id="ucapan-bahasa"')
            ->assertSeeHtml('<label for="ucapan-cari"')
            ->assertSeeHtml('role="status"')
            ->assertDontSeeHtml('text-red-500');
    }

    public function test_conversation_language_choice_exposes_pressed_state(): void
    {
        Livewire::test(Picker::class)
            ->assertSeeHtml('aria-pressed="true"')
            ->assertSeeHtml('aria-pressed="false"');
    }

    public function test_low_contrast_utility_classes_are_gone_from_views(): void
    {
        $views = collect(glob(resource_path('views/livewire/{,*/,*/*/}*.blade.php'), GLOB_BRACE));

        foreach ($views as $path) {
            $html = file_get_contents($path);
            $this->assertDoesNotMatchRegularExpression('#text-white/(50|60|70)\b|text-red-500|bg-red-500|text-ink-faint/70#', $html, $path);
        }
    }

    public function test_compact_layout_keeps_named_header_main_and_footer(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('id="konten"', false)
            ->assertSee('<header', false)
            ->assertSee('<footer', false);
    }
}
