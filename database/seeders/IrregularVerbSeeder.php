<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the 183-verb reference dataset into irregular_verbs_reference — a
 * separate table from the historical irregular_verbs curriculum. Indonesian meanings
 * already ship in the source file, so no translation step is needed.
 */
class IrregularVerbSeeder extends Seeder
{
    public function run(): void
    {
        $verbs = json_decode(
            file_get_contents(storage_path('app/seed-data/irregular_verbs_183_complete.json')),
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $now = now();

        $rows = array_map(fn (array $v) => [
            'base_form' => $v['base'],
            'past_form' => $v['past'],
            'past_participle' => $v['past_participle'],
            'ing_form' => $v['ing_form'],
            'meaning_id' => $v['meaning_id'],
            'frequency_rank' => $v['rank'],
            'created_at' => $now,
            'updated_at' => $now,
        ], $verbs);

        DB::table('irregular_verbs_reference')->upsert(
            $rows,
            ['base_form'],
            ['past_form', 'past_participle', 'ing_form', 'meaning_id', 'frequency_rank', 'updated_at']
        );
    }
}
