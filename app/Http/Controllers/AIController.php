<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

use App\Traits\AutoCorrectTrait;

class AIController extends Controller
{
    use AutoCorrectTrait;
    /**
     * Convert tone of a message using AI API.
     * Supports: OpenAI, Groq, Gemini (configurable via .env)
     */
    public function convertTone(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:1000',
        ]);

        $originalMessage = trim($request->message);

        try {
            $result = $this->callAI($originalMessage, 'tone');
            return response()->json($result);
        } catch (\Exception $e) {
            Log::error('AI Tone Conversion Failed: ' . $e->getMessage());

            $fixedMessage = $this->basicCorrection($originalMessage);

            // Graceful fallback
            return response()->json([
                'corrected'   => $fixedMessage,
                'suggestions' => [
                    ['tone' => 'formal',       'text' => $this->simpleFallback($originalMessage, 'formal')],
                    ['tone' => 'friendly',     'text' => $this->simpleFallback($originalMessage, 'friendly')],
                    ['tone' => 'professional', 'text' => $this->simpleFallback($originalMessage, 'professional')],
                    ['tone' => 'funny',        'text' => $this->simpleFallback($originalMessage, 'funny')],
                ],
                'fallback' => true,
            ]);
        }
    }

    /**
     * Translate a message into a target language.
     */
    public function translate(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'target_language' => 'required|string|max:50',
        ]);

        $message = trim($request->message);
        $targetLang = trim($request->target_language);

        try {
            $result = $this->callAI($message, 'translation', $targetLang);
            return response()->json([
                'success'    => true,
                'original'   => $message,
                'translated' => $result['translated'],
                'language'   => $targetLang
            ]);
        } catch (\Exception $e) {
            Log::error('AI Translation Failed: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'error'   => 'Translation failed. Please try again later.',
                'message' => $message // Return original message as fallback
            ], 500);
        }
    }

    private function callAI(string $message, string $mode, string $extra = ''): array
    {
        $provider = config('ai.provider', 'groq');

        return match ($provider) {
            'openai' => $this->callOpenAI($message, $mode, $extra),
            'groq'   => $this->callGroq($message, $mode, $extra),
            'gemini' => $this->callGemini($message, $mode, $extra),
            default  => throw new \Exception("Unknown AI provider: $provider"),
        };
    }

    // ── OpenAI ────────────────────────────────────────────────────────────────

    private function callOpenAI(string $message, string $mode, string $extra): array
    {
        $prompt = ($mode === 'tone') ? $this->systemPrompt() : $this->translationSystemPrompt($extra);

        $response = Http::withToken(config('ai.openai_key'))
            ->withoutVerifying()
            ->timeout(15)
            ->post('https://api.openai.com/v1/chat/completions', [
                'model'       => 'gpt-4o-mini',
                'temperature' => ($mode === 'tone') ? 0.7 : 0.3, // Lower temperature for translation
                'messages'    => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user',   'content' => $message],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('OpenAI API error: ' . $response->body());
        }

        $text = $response->json('choices.0.message.content');
        return ($mode === 'tone') ? $this->parseAIResponse($text) : ['translated' => trim($text)];
    }

    // ── Groq ──────────────────────────────────────────────────────────────────

    private function callGroq(string $message, string $mode, string $extra): array
    {
        $prompt = ($mode === 'tone') ? $this->systemPrompt() : $this->translationSystemPrompt($extra);

        $response = Http::withToken(config('ai.groq_key'))
            ->withoutVerifying()
            ->timeout(15)
            ->post('https://api.groq.com/openai/v1/chat/completions', [
                'model'       => 'llama-3.1-8b-instant',
                'temperature' => ($mode === 'tone') ? 0.7 : 0.3,
                'messages'    => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user',   'content' => $message],
                ],
            ]);

        if ($response->failed()) {
            throw new \Exception('Groq API error: ' . $response->body());
        }

        $text = $response->json('choices.0.message.content');
        return ($mode === 'tone') ? $this->parseAIResponse($text) : ['translated' => trim($text)];
    }

    // ── Gemini ────────────────────────────────────────────────────────────────

    private function callGemini(string $message, string $mode, string $extra): array
    {
        $apiKey   = config('ai.gemini_key');
        $prompt   = ($mode === 'tone') ? $this->systemPrompt() : $this->translationSystemPrompt($extra);

        $response = Http::withoutVerifying()
            ->timeout(15)
            ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$apiKey}", [
                'contents' => [[
                    'parts' => [[
                        'text' => $prompt . "\n\nUser message:\n" . $message,
                    ]],
                ]],
            ]);

        if ($response->failed()) {
            throw new \Exception('Gemini API error: ' . $response->body());
        }

        $text = $response->json('candidates.0.content.parts.0.text');
        return ($mode === 'tone') ? $this->parseAIResponse($text) : ['translated' => trim($text)];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function systemPrompt(): string
    {
        return <<<PROMPT
You are a messaging assistant. Given a user's message, return ONLY a valid JSON object with no extra text, no markdown, no backticks.

The JSON must have exactly this structure:
{
  "corrected": "The auto-corrected version of the original message",
  "suggestions": [
    { "tone": "formal",       "text": "Rewritten in formal tone" },
    { "tone": "friendly",     "text": "Rewritten in friendly tone" },
    { "tone": "professional", "text": "Rewritten in professional tone" },
    { "tone": "funny",        "text": "Rewritten in funny/casual tone" }
  ]
}

Rules:
- corrected: fix grammar/spelling only, keep original meaning
- Each suggestion must be a short, natural sentence (max 2 sentences)
- Do NOT add explanations, just the JSON
PROMPT;
    }

    private function translationSystemPrompt(string $targetLanguage): string
    {
        return <<<PROMPT
You are a translator. Translate the following message into {$targetLanguage}.
Return ONLY the translated text. Do not add any explanations, notes, or quotes.
Preserve the tone and any emojis present in the original message.
PROMPT;
    }

    private function parseAIResponse(string $text): array
    {
        // Strip markdown code fences if present
        $text = preg_replace('/^```(?:json)?\s*/i', '', trim($text));
        $text = preg_replace('/\s*```$/', '', $text);
        $text = trim($text);

        $data = json_decode($text, true);

        if (!$data || !isset($data['corrected'], $data['suggestions'])) {
            throw new \Exception('Invalid AI response format: ' . $text);
        }

        // Ensure we have valid suggestions array
        $suggestions = array_filter($data['suggestions'], fn($s) => isset($s['tone'], $s['text']));

        return [
            'corrected'   => $data['corrected'],
            'suggestions' => array_values($suggestions),
        ];
    }

    private function simpleFallback(string $message, string $tone): string
    {
        $corrected = $this->autoCorrect($message);

        return match ($tone) {
            'formal'       => ucfirst($corrected) . (str_ends_with($corrected, '.') ? '' : '.'),
            'friendly'     => 'Hey! ' . $corrected . ' 😊',
            'professional' => 'I wanted to let you know: ' . $corrected,
            'funny'        => $corrected . ' (no cap lol)',
            default        => $corrected,
        };
    }

    private function basicCorrection(string $message): string
    {
        return $this->autoCorrect($message);
    }
}
