<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use App\Dashboard\Http\HttpClientInterface;
use Marko\Log\Contracts\LoggerInterface;

class AgentChatService
{
    public function __construct(
        private readonly ApiKeyResolver $apiKeyResolver,
        private readonly AgentPromptRegistry $promptRegistry,
        private readonly HttpClientInterface $http,
        private readonly LoggerInterface $logger,
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
            $this->logger->warning('OpenRouter API key not configured', ['user_id' => $userId]);
            return [
                'role' => 'assistant',
                'content' => '⚠️ OpenRouter API key is not configured. Please add `OPENROUTER_API_KEY` to your environment to enable AI agents.',
            ];
        }

        $this->logger->info('Agent chat request', [
            'user_id' => $userId,
            'modality' => $modality,
            'model' => $modelConfig['model'] ?? 'default',
        ]);

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
            $this->logger->error('OpenRouter chat API returned null', [
                'user_id' => $userId,
                'modality' => $modality,
            ]);
            return null;
        }

        $this->logger->debug('OpenRouter chat response', [
            'user_id' => $userId,
            'status' => $response['status'] ?? 'unknown',
        ]);

        $decoded = json_decode($response['body'], true);
        if (!is_array($decoded)) {
            $this->logger->error('OpenRouter chat response decode failed', [
                'user_id' => $userId,
                'body_preview' => substr($response['body'] ?? '', 0, 200),
            ]);
            return null;
        }

        $content = $decoded['choices'][0]['message']['content'] ?? null;

        if ($content === null) {
            $this->logger->error('OpenRouter chat response missing content', [
                'user_id' => $userId,
                'response_keys' => array_keys($decoded),
            ]);
            return null;
        }

        $this->logger->info('Agent chat completed', [
            'user_id' => $userId,
            'modality' => $modality,
            'content_length' => strlen($content),
        ]);

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
     * Stream a text response token-by-token via OpenRouter SSE.
     * Calls $onToken for each token chunk as it arrives from the API.
     *
     * @param array<int, array{role: string, content: string}> $history
     * @param array<int, array<string, mixed>> $kbContext
     * @param array{model?: string, temperature?: float, max_tokens?: int} $modelConfig
     * @param callable(string): void $onToken
     */
    public function streamChat(
        int $userId,
        string $modality,
        string $userMessage,
        array $history,
        array $kbContext,
        callable $onToken,
        array $modelConfig = [],
    ): void {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey)) {
            $onToken('⚠️ OpenRouter API key is not configured. Please add `OPENROUTER_API_KEY` to your environment to enable AI agents.');
            return;
        }

        $this->logger->info('Agent chat stream request', [
            'user_id' => $userId,
            'modality' => $modality,
            'model' => $modelConfig['model'] ?? 'default',
        ]);

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

        $body = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => $temperature,
            'max_tokens' => $maxTokens,
            'stream' => true,
        ]);

        $buffer = '';

        $ch = curl_init('https://openrouter.ai/api/v1/chat/completions');
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apiKey,
            'HTTP-Referer: https://launchpilot.ai',
            'X-Title: LaunchPilot',
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $data) use (&$buffer, $onToken): int {
            $buffer .= $data;

            while (($pos = strpos($buffer, "\n\n")) !== false) {
                $event = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 2);

                foreach (explode("\n", $event) as $line) {
                    if (str_starts_with($line, 'data: ')) {
                        $json = substr($line, 6);

                        if ($json === '[DONE]') {
                            break 2;
                        }

                        $decoded = json_decode($json, true);
                        if (is_array($decoded)) {
                            $content = $decoded['choices'][0]['delta']['content'] ?? '';
                            if ($content !== '') {
                                $onToken($content);
                            }
                        }
                    }
                }
            }

            return strlen($data);
        });

        curl_exec($ch);
        curl_close($ch);

        $this->logger->info('Agent chat stream completed', ['user_id' => $userId]);
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
