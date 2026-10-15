<?php

namespace Tests\Feature;

use Tests\TestCase;

/** Home page follows docs/pedoman-teks-pembaca-layar.md (rule numbers in the test names). */
class LandingScreenReaderTextTest extends TestCase
{
    private function html(): string
    {
        return $this->get('/')->assertOk()->getContent();
    }

    public function test_rule_1_section_headings_name_their_section(): void
    {
        $html = $this->html();

        foreach ([
            'judul-masalah' => 'Masalah: ',
            'judul-cara-kerja' => 'Cara kerja: ',
            'judul-fitur' => 'Fitur utama: ',
            'judul-coba' => 'Coba langsung: ',
            'judul-aksesibilitas' => 'Aksesibilitas: ',
            'judul-status' => 'Status pengembangan: ',
        ] as $id => $prefix) {
            $this->assertMatchesRegularExpression('#<h2 id="'.$id.'"[^>]*><span class="sr-only">'.preg_quote($prefix, '#').'</span>#', $html, $id);
        }

        // The badge repeating that name is hidden, so it is not read twice.
        $this->assertSame(6, preg_match_all('#<p class="inline-block rounded-full[^"]*" aria-hidden="true" data-pil>#', $html));
    }

    public function test_rule_2_controls_name_action_and_object(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('aria-label="Dengarkan kalimat contoh Mandarin"', $html);
        $this->assertStringContainsString('aria-label="Dengarkan kalimat contoh Inggris"', $html);
        $this->assertStringContainsString('TUTURNUSA<span class="sr-only">, beranda</span>', $html);
        $this->assertDoesNotMatchRegularExpression('#>\s*(klik di sini|selengkapnya)\s*<#i', $html);
    }

    public function test_rule_3_no_position_words(): void
    {
        $html = mb_strtolower(strip_tags($this->html()));

        foreach (['di samping', 'di sebelah kanan', 'di sebelah kiri', 'tombol kuning', 'titik hijau'] as $phrase) {
            $this->assertStringNotContainsString($phrase, $html, $phrase);
        }
    }

    public function test_rule_4_symbols_are_read_as_words(): void
    {
        $html = $this->html();

        foreach ([
            '0 / 4.991' => '0 dari 4991',
            '~16%' => 'Sekitar 16 persen',
            '1–3' => '1 sampai 3',
            'Dengarkan / Ketik' => 'Dengarkan atau ketik',
            'Pelatih Bahasa Mandarin &amp; Inggris' => 'Pelatih bahasa Mandarin dan Inggris',
            'Zoom 400%' => 'Zoom 400 persen',
        ] as $shown => $said) {
            $this->assertStringContainsString('<span aria-hidden="true">'.$shown.'</span><span class="sr-only">'.$said.'</span>', $html, $shown);
        }
        $this->assertStringContainsString('Keyboard dan pembaca layar', $html);
    }

    public function test_rule_5_abbreviations_explained_once(): void
    {
        $html = $this->html();

        $this->assertSame(1, substr_count($html, 'HSK (standar ujian bahasa Mandarin)'));
        $this->assertSame(1, substr_count($html, 'AI (kecerdasan buatan)'));
        $this->assertStringContainsString('Pedoman aksesibilitas web yang kami kejar', $html);
    }

    public function test_rule_6_hanzi_tagged_pinyin_not(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('<span lang="zh-Hans">我很高兴。</span>', $html);
        $this->assertStringContainsString('<span class="text-ink-soft">(wǒ hěn gāoxìng)</span>', $html);
        $this->assertStringNotContainsString('zh-Latn', $html);
        $this->assertDoesNotMatchRegularExpression('#alt="[^"]*\p{Han}#u', $html);
    }

    public function test_rule_8_example_is_announced_as_example(): void
    {
        $html = $this->html();

        $this->assertStringContainsString('<span class="sr-only">Contoh hasil: </span>Jawaban sudah benar!', $html);
        $this->assertStringContainsString('Jawabanmu <span class="font-normal">(contoh)</span>', $html);
    }

    public function test_guideline_document_exists_and_is_linked(): void
    {
        $doc = file_get_contents(base_path('docs/pedoman-teks-pembaca-layar.md'));

        $this->assertStringContainsString('belum diuji dengan pengguna tunanetra', $doc);
        $this->assertSame(10, preg_match_all('/^## \d+\. /m', $doc));
        $this->assertStringContainsString('docs/pedoman-teks-pembaca-layar.md', file_get_contents(base_path('README.md')));
    }
}
