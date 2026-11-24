<?php

namespace Tests\Feature;

use Tests\TestCase;

class BantuanPageTest extends TestCase
{
    public function test_bantuan_is_public(): void
    {
        $this->get('/bantuan')->assertOk();
    }

    public function test_bantuan_page_explains_keyboard_voice_and_display_settings(): void
    {
        $html = $this->get('/bantuan')
            ->assertOk()
            ->assertSee('Memakai dengan keyboard dan pembaca layar')
            ->assertSee('Memakai input suara')
            ->assertSee('Chrome atau Edge')
            ->assertSee('Mengatur tampilan')
            ->getContent();

        foreach (['Tab', 'Shift</kbd>+<kbd>Tab', 'Enter', 'Shift</kbd>+<kbd>Enter', 'Escape'] as $key) {
            $this->assertStringContainsString("<kbd>{$key}</kbd>", $html);
        }
        $this->assertSame(1, substr_count($html, '<h1'));
    }

    public function test_statement_texts_are_exact_and_no_disability_claim(): void
    {
        $this->get('/bantuan')
            ->assertOk()
            ->assertSee('Belajar bahasa dengan keyboard dan suara. Dirancang agar mudah diakses.')
            ->assertSee('Pernyataan aksesibilitas (draf): kami berupaya mengikuti pedoman WCAG 2.2 AA. Aplikasi ini dirancang untuk pembaca layar; pengujian oleh tim dengan Narator sedang berlangsung dan belum dilakukan dengan pengguna tunanetra. Masukan Anda sangat kami harapkan.')
            ->assertDontSee('diuji oleh tim dengan pembaca layar Narator')
            ->assertDontSee('membantu penyandang disabilitas');
    }

    public function test_bantuan_is_linked_from_layout_footer(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();
        $link = 'href="'.route('bantuan').'"';

        $footer = substr($html, strpos($html, '<footer'), strpos($html, '</footer>') - strpos($html, '<footer'));
        $this->assertStringContainsString($link, $footer);
        $this->assertStringContainsString('Bantuan dan aksesibilitas', $footer);
        $this->assertStringContainsString(route('bantuan').'#tampilan', $footer);
        $this->assertStringContainsString('Ukuran teks', $footer);
    }

    public function test_bantuan_has_text_size_fieldset_with_three_choices(): void
    {
        $html = $this->get('/bantuan')->assertOk()->getContent();

        $this->assertStringContainsString('id="tampilan"', $html);
        $this->assertStringContainsString('<fieldset', $html);
        $this->assertStringContainsString('Ukuran teks', $html);
        $this->assertStringContainsString('value="normal"', $html);
        $this->assertStringContainsString('value="besar"', $html);
        $this->assertStringContainsString('value="sangat-besar"', $html);
        $this->assertStringContainsString('min-h-[3.5rem]', $html);
        $this->assertStringContainsString('x-data="displaySettings()"', $html);
    }
}
