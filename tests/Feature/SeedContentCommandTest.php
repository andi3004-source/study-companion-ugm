<?php

namespace Tests\Feature;

use App\Console\Commands\SeedContent;
use App\Models\MandarinWord;
use App\Models\User;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\WithSmallVocabularyFixtures;
use Tests\TestCase;

class SeedContentCommandTest extends TestCase
{
    use RefreshDatabase, WithSmallVocabularyFixtures;

    private const TABLES = ['mandarin_words', 'irregular_verbs', 'week_topics'];

    private function counts(array $tables): array
    {
        return collect($tables)->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()])->all();
    }

    public function test_reference_data_is_read_from_storage_seed_data(): void
    {
        $this->assertSame('app/seed-data/reference', ReferenceDataSeeder::DATA_PATH);
        $this->assertFileExists(storage_path('app/seed-data/reference/mandarin_words.json'));
        $this->assertDirectoryDoesNotExist(database_path('seeders/data'));
    }

    public function test_reference_seeder_is_idempotent_and_keeps_ids(): void
    {
        $this->seed(ReferenceDataSeeder::class);
        $before = $this->counts(self::TABLES);
        $firstId = MandarinWord::orderBy('id')->value('id');

        $this->seed(ReferenceDataSeeder::class);

        $this->assertSame($before, $this->counts(self::TABLES));
        $this->assertSame($firstId, MandarinWord::orderBy('id')->value('id'));
        foreach ($before as $table => $count) {
            $this->assertGreaterThan(0, $count, $table);
        }
    }

    public function test_command_seeds_content_only_and_can_run_twice(): void
    {
        foreach (SeedContent::SEEDERS as $files) {
            foreach ($files as $file) {
                if (! is_file(storage_path($file))) {
                    $this->artisan('app:seed-content')
                        ->expectsOutputToContain('dilewati')
                        ->assertFailed();
                    $this->assertGreaterThan(0, MandarinWord::count());

                    return;
                }
            }
        }

        $content = [...self::TABLES, 'mandarin_hsk_words', 'irregular_verbs_reference', 'english_vocab'];

        $this->artisan('app:seed-content')->expectsOutputToContain('Konten siap.')->assertSuccessful();
        $before = $this->counts($content);
        $this->assertGreaterThan(10000, $before['english_vocab']);
        $this->assertGreaterThan(0, $before['mandarin_hsk_words']);
        $this->assertGreaterThan(0, $before['irregular_verbs_reference']);
        $this->artisan('app:seed-content')->assertSuccessful();

        $this->assertSame($before, $this->counts($content));
        $this->assertSame(0, User::count());
    }

    public function test_command_seeds_content_with_fixtures_and_is_idempotent(): void
    {
        $this->useSmallVocabularyFixtures();

        try {
            $content = [...self::TABLES, 'mandarin_hsk_words', 'irregular_verbs_reference', 'english_vocab'];

            $this->artisan('app:seed-content')->expectsOutputToContain('Konten siap.')->assertSuccessful();
            $before = $this->counts($content);
            $this->assertGreaterThan(0, $before['english_vocab']);
            $this->assertGreaterThan(0, $before['mandarin_hsk_words']);
            $this->assertGreaterThan(0, $before['irregular_verbs_reference']);
            $this->artisan('app:seed-content')->assertSuccessful();

            $this->assertSame($before, $this->counts($content));
            $this->assertSame(0, User::count());
        } finally {
            $this->removeSmallVocabularyFixtures();
        }
    }

    public function test_command_is_registered_with_the_expected_name(): void
    {
        $this->assertArrayHasKey('app:seed-content', $this->app[Kernel::class]->all());
    }
}
