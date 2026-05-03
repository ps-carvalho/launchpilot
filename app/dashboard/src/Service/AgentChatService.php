<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Http\HttpClientInterface;

class AgentChatService
{
    public function __construct(
        private readonly ApiKeyResolver $apiKeyResolver,
        private readonly AgentPromptRegistry $promptRegistry,
        private readonly HttpClientInterface $http,
    ) {}

    /**
     * Generate text response via OpenRouter chat completions.
     *
     * @param array<int, array{role: string, content: string}> $history
     * @param array<int, array<string, mixed>> $kbContext
     * @param array{model?: string, temperature?: float, max_tokens?: int} $modelConfig
     * @return array{role: string, content: string}|null
     */
    public function chat(
        int $userId,
        string $modality,
        string $userMessage,
        array $history,
        array $kbContext,
        array $modelConfig = [],
    ): ?array {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey)) {
            return [
                'role' => 'assistant',
                'content' => '⚠️ OpenRouter API key is not configured. Please add `OPENROUTER_API_KEY` to your environment to enable AI agents.',
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($userId, $modality, $kbContext);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $model = $modelConfig['model'] ?? 'meta-llama/llama-3.3-70b-instruct';
        $temperature = $modelConfig['temperature'] ?? 0.7;
        $maxTokens = $modelConfig['max_tokens'] ?? 2000;

        $response = $this->http->post(
            'https://openrouter.ai/api/v1/chat/completions',
            json_encode([
                'model' => $model,
                'messages' => $messages,
                'temperature' => $temperature,
                'max_tokens' => $maxTokens,
            ]),
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => 'https://launchpilot.ai',
                'X-Title' => 'LaunchPilot',
            ],
            60
        );

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            return null;
        }

        return [
            'role' => 'assistant',
            'content' => $content,
        ];
    }

    /**
     * Generate an image via OpenRouter image-generation models.
     *
     * @param array{model?: string, temperature?: float, max_tokens?: int} $modelConfig
     * @return array{role: string, content: string, images: array<int, string>}|null
     */
    public function generateImage(
        int $userId,
        string $prompt,
        array $modelConfig = [],
    ): ?array {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey)) {
            return [
                'role' => 'assistant',
                'content' => '⚠️ OpenRouter API key is not configured.',
                'images' => [],
            ];
        }

        $model = $modelConfig['model'] ?? 'black-forest-labs/flux-2-schnell';

        $body = [
            'model' => $model,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
            ],
            'modalities' => ['image', 'text'],
        ];

        // Some models only support image output without text
        if (str_starts_with($model, 'black-forest-labs/flux') || str_starts_with($model, 'sourceful/')) {
            $body['modalities'] = ['image'];
        }

        $response = $this->http->post(
            'https://openrouter.ai/api/v1/chat/completions',
            json_encode($body),
            [
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $apiKey,
                'HTTP-Referer' => 'https://launchpilot.ai',
                'X-Title' => 'LaunchPilot',
            ],
            120
        );

        if ($response === null) {
            return null;
        }

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            return null;
        }

        $message = $decoded['choices'][0]['message'] ?? null;
        if ($message === null) {
            return null;
        }

        $content = $message['content'] ?? '';
        $images = [];

        if (!empty($message['images']) && is_array($message['images'])) {
            foreach ($message['images'] as $img) {
                if (is_string($img)) {
                    $images[] = $img;
                } elseif (is_array($img) && !empty($img['data'])) {
                    $images[] = $img['data'];
                }
            }
        }

        return [
            'role' => 'assistant',
            'content' => $content ?: 'Image generated.',
            'images' => $images,
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $kbContext
     */
    private function buildSystemPrompt(int $userId, string $modality, array $kbContext): string
    {
        $customPrompt = $this->promptRegistry->get($userId, 'agent')
            ?? $this->promptRegistry->get($userId, $modality)
            ?? $this->promptRegistry->get($userId, 'social')
            ?? $this->promptRegistry->get($userId, 'content')
            ?? $this->promptRegistry->get($userId, 'seo')
            ?? $this->promptRegistry->get($userId, 'media');
        $kbText = $this->formatKbContext($kbContext);

        if ($customPrompt !== null) {
            return $customPrompt . "\n\nBUSINESS CONTEXT:\n" . $kbText;
        }

        $prompts = [
            'text' => "You are the LaunchPilot Marketing Agent. You are an expert at creating marketing content, strategy, and copy for small businesses.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Write engaging, platform-appropriate content\n- For social: short, punchy, with hashtags\n- For blogs: well-structured with headings and SEO in mind\n- For strategy: suggest angles, calendars, themes, and audience targeting\n- For SEO: specific, actionable advice with keywords and content gaps\n- Always provide ready-to-use output",

            'image' => "You are the LaunchPilot Image Prompt Engineer. You help users create detailed, effective prompts for image generation.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Refine user prompts for maximum image quality\n- Add detail about composition, lighting, style, and mood\n- Keep prompts concise but descriptive\n- The refined prompt will be sent directly to an image generation model",

            'video' => "You are the LaunchPilot Video Script Writer. You write scripts and shot lists for marketing videos.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Write scripts with hooks, clear messaging, and CTAs\n- Include shot lists with timing, visual direction, and on-screen text cues\n- Specify voiceover text separately from visual directions\n- Provide read-time estimates\n- For free-tier users: also suggest tools they can use to create the video",
        ];

        return $prompts[$modality] ?? $prompts['text'];
    }

    /**
     * @param array<int, array<string, mixed>> $kbContext
     */
    private function formatKbContext(array $kbContext): string
    {
        $text = '';
        foreach ($kbContext as $chunk) {
            $text .= "\n---\nSource: " . ($chunk['original_name'] ?? 'Unknown') . "\n";
            $text .= $chunk['chunk_text'] ?? '';
        }
        return $text;
    }
}
