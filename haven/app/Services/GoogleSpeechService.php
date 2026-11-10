<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Cloud Speech-to-Text and Text-to-Speech (REST). Key stays server-side.
 */
class GoogleSpeechService
{
    public function isConfigured(): bool
    {
        return filled(config('services.google_cloud.key'));
    }

    private function headers(): array
    {
        return ['X-Goog-Api-Key' => config('services.google_cloud.key')];
    }

    /**
     * Transcribe a short (<= ~60 s) browser recording (WebM/Opus or OGG/Opus or WAV).
     *
     * @return array{ok:bool, transcript:?string, confidence:?float, error:?string}
     */
    public function transcribe(string $audioBytes, string $mime): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'transcript' => null, 'confidence' => null, 'error' => 'not_configured'];
        }

        $mime = strtolower($mime);
        $config = [
            'languageCode' => config('services.google_cloud.stt_language', 'en-US'),
            'enableAutomaticPunctuation' => true,
            'model' => 'latest_short',
        ];
        if (str_contains($mime, 'webm')) {
            $config['encoding'] = 'WEBM_OPUS';
        } elseif (str_contains($mime, 'ogg')) {
            $config['encoding'] = 'OGG_OPUS';
        } elseif (str_contains($mime, 'wav') || str_contains($mime, 'wave')) {
            // WAV header carries the sample rate / encoding; omit both.
            unset($config['model']);
        } else {
            return ['ok' => false, 'transcript' => null, 'confidence' => null, 'error' => 'unsupported_format'];
        }

        try {
            $res = Http::timeout(30)->withHeaders($this->headers())
                ->post('https://speech.googleapis.com/v1/speech:recognize', [
                    'config' => $config,
                    'audio' => ['content' => base64_encode($audioBytes)],
                ]);

            if (! $res->successful()) {
                Log::warning('Google STT error', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);

                return ['ok' => false, 'transcript' => null, 'confidence' => null, 'error' => 'upstream_'.$res->status()];
            }

            $parts = [];
            $conf = [];
            foreach ($res->json('results', []) as $r) {
                $alt = $r['alternatives'][0] ?? null;
                if ($alt) {
                    $parts[] = trim((string) ($alt['transcript'] ?? ''));
                    if (isset($alt['confidence'])) {
                        $conf[] = (float) $alt['confidence'];
                    }
                }
            }

            return [
                'ok' => true,
                'transcript' => trim(implode(' ', array_filter($parts))),
                'confidence' => $conf ? round(array_sum($conf) / count($conf), 3) : null,
                'error' => null,
            ];
        } catch (\Throwable $e) {
            Log::warning('Google STT exception: '.$e->getMessage());

            return ['ok' => false, 'transcript' => null, 'confidence' => null, 'error' => 'unavailable'];
        }
    }

    /**
     * Synthesize speech. Returns base64 MP3.
     *
     * @return array{ok:bool, audio_base64:?string, mime:string, error:?string}
     */
    public function synthesize(string $text): array
    {
        if (! $this->isConfigured()) {
            return ['ok' => false, 'audio_base64' => null, 'mime' => 'audio/mpeg', 'error' => 'not_configured'];
        }

        $text = mb_substr(trim($text), 0, 1500);
        if ($text === '') {
            return ['ok' => false, 'audio_base64' => null, 'mime' => 'audio/mpeg', 'error' => 'empty_text'];
        }

        try {
            $res = Http::timeout(20)->withHeaders($this->headers())
                ->post('https://texttospeech.googleapis.com/v1/text:synthesize', [
                    'input' => ['text' => $text],
                    'voice' => [
                        'languageCode' => config('services.google_cloud.tts_language', 'en-US'),
                        'name' => config('services.google_cloud.tts_voice', 'en-US-Neural2-D'),
                    ],
                    'audioConfig' => ['audioEncoding' => 'MP3'],
                ]);

            if (! $res->successful() || ! $res->json('audioContent')) {
                Log::warning('Google TTS error', ['status' => $res->status(), 'body' => mb_substr($res->body(), 0, 500)]);

                return ['ok' => false, 'audio_base64' => null, 'mime' => 'audio/mpeg', 'error' => 'upstream_'.$res->status()];
            }

            return ['ok' => true, 'audio_base64' => $res->json('audioContent'), 'mime' => 'audio/mpeg', 'error' => null];
        } catch (\Throwable $e) {
            Log::warning('Google TTS exception: '.$e->getMessage());

            return ['ok' => false, 'audio_base64' => null, 'mime' => 'audio/mpeg', 'error' => 'unavailable'];
        }
    }
}

