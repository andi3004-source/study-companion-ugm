<?php

namespace App\Support;

/**
 * Decides between the (up to 3) alternatives SpeechRecognition returns for
 * one utterance. Every alternative is parsed; raw texts are never compared,
 * so "Ucapan" vs "ucapan." or "buka ucapan" vs "ucapan" don't count as a doubt.
 *
 *   accept   the top alternative is a known command and no other alternative
 *            is a different known command: act on it
 *   choose   two alternatives are different known commands: ask "satu, A,
 *            atau dua, B?" (type 'clarify' with two labelled candidates)
 *   confirm  the top alternative is not known but exactly one other is:
 *            ask "Saya dengar: <that alternative>. Benar?"; 'heard_index'
 *            says which alternative (the client has the text)
 *   none     nothing is known: the top parse as is (unknown or clarify)
 *
 * Transcripts are not stored, logged or echoed back.
 */
class VoiceAlternatives
{
    public const MAX = 3;

    /** @param  list<string>  $transcripts  best first */
    public static function decide(array $transcripts): array
    {
        $results = array_map(fn ($t) => VoiceCommandParser::parse((string) $t), array_slice(array_values($transcripts), 0, self::MAX));

        if ($results === []) {
            return VoiceCommandParser::parse('') + ['decision' => 'none'];
        }

        // Distinct known commands, in alternative order.
        $known = [];
        foreach ($results as $i => $result) {
            if (! in_array($result['type'], ['unknown', 'clarify'], true)) {
                $known[self::key($result)] ??= ['index' => $i, 'result' => $result];
            }
        }
        $known = array_values($known);

        if (count($known) >= 2) {
            $candidates = array_map(
                fn ($k) => $k['result'] + ['label' => VoiceCommandParser::label($k['result'])],
                array_slice($known, 0, 2),
            );

            return ['type' => 'clarify', 'target' => null, 'index' => null, 'decision' => 'choose', 'candidates' => $candidates];
        }

        if (count($known) === 1) {
            return $known[0]['index'] === 0
                ? $known[0]['result'] + ['decision' => 'accept']
                : $known[0]['result'] + ['decision' => 'confirm', 'heard_index' => $known[0]['index']];
        }

        return $results[0] + ['decision' => 'none'];
    }

    private static function key(array $result): string
    {
        return $result['type'].'|'.$result['target'].'|'.$result['index'];
    }
}
