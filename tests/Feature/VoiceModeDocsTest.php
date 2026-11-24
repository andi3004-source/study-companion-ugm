<?php

namespace Tests\Feature;

use Tests\TestCase;

class VoiceModeDocsTest extends TestCase
{
    public function test_bantuan_lists_voice_commands_and_honest_limits(): void
    {
        $this->get('/bantuan')->assertOk()
            ->assertSeeInOrder(['Mode suara', 'Perintah', 'daftar tindakan', 'selesai isi', 'Batas'])
            ->assertSee('Pembaca layar dan keyboard tetap jalur utama.')
            ->assertSee('Hanya berjalan di Chrome atau Edge dan butuh internet.')
            ->assertSee('Suara Mandarin harus terpasang di perangkat')
            ->assertSee('satu ujaran per tekan')
            ->assertSee('Belum diuji dengan pengguna tunanetra.')
            ->assertDontSee('membantu penyandang disabilitas');
    }

    public function test_readme_documents_voice_mode_honestly(): void
    {
        $readme = file_get_contents(base_path('README.md'));
        $section = substr($readme, strpos($readme, '## Mode suara'));
        $next = strpos($section, "\n## ", 3);
        $section = $next === false ? $section : substr($section, 0, $next);

        foreach (['Chrome atau Edge', 'butuh internet', 'penyedia', 'Akurasi bervariasi', 'suara Mandarin harus terpasang', 'satu', 'Belum diuji dengan pengguna', 'Pembaca layar tetap jalur utama'] as $phrase) {
            $this->assertStringContainsString($phrase, $section, $phrase);
        }
    }
}
