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
     * @param array<int, array{role: string, content: string}> $history
     * @param array<int, array<string, mixed>> $kbContext
     * @param array{model?: string, temperature?: float, max_tokens?: int} $modelConfig
     * @return array{role: string, content: string}|null
     */
    public function chat(
        int $userId,
        string $agentType,
        string $userMessage,
        array $history,
        array $kbContext,
        array $modelConfig = [],
    ): ?array {
        $apiKey = $this->apiKeyResolver->resolve($userId);

        if (empty($apiKey) || $apiKey === 'sk-or-v1-REPLACE_ME') {
            return [
                'role' => 'assistant',
                'content' => "⚠️ OpenRouter API key is not configured. Please add `OPENROUTER_API_KEY` to your `.env` file to enable AI agents.",
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($userId, $agentType, $kbContext);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $model = $modelConfig['model'] ?? 'openai/gpt-4o-mini';
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
     * @param array<int, array<string, mixed>> $kbContext
     */
    private function buildSystemPrompt(int $userId, string $agentType, array $kbContext): string
    {
        $customPrompt = $this->promptRegistry->get($userId, $agentType);
        $kbText = $this->formatKbContext($kbContext);

        if ($customPrompt !== null) {
            return $customPrompt . "\n\nBUSINESS CONTEXT:\n" . $kbText;
        }

        $prompts = [
            'social' => "You are a Social Media Marketing Agent for LaunchPilot. Your job is to write engaging, platform-appropriate social media posts based on the user's business context.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Write short, punchy, engaging posts\n- Match the tone to the platform (LinkedIn = professional, Facebook = friendly, general = adaptable)\n- Include relevant hashtags when appropriate\n- Always provide the post text ready to copy-paste\n- If the user asks for multiple posts, number them clearly",

            'content' => "You are a Content Strategist for LaunchPilot. You are an expert at both writing long-form content AND developing content strategy.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- For writing tasks: produce compelling, well-structured content with headings, bullet points, and readability in mind\n- For strategy tasks: suggest content angles, calendars, themes, and audience targeting\n- Include catchy titles when appropriate\n- Optimize for SEO naturally (no keyword stuffing)\n- Match tone to the audience described in the business context\n- If asked for ideas/brainstorming: be creative, suggest features, customer profiles, positioning, and messaging angles",

            'seo' => "You are an SEO Agent for LaunchPilot. Your job is to analyze websites and provide actionable SEO recommendations.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Provide specific, actionable SEO advice\n- Suggest keywords to target based on the business context\n- Recommend content gaps to fill\n- Give concrete steps the user can take to improve their search rankings\n- Be specific, not generic",

            'media' => "You are a Media Strategist for LaunchPilot. Your job is to help users plan and conceptualize visual and audio content.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- For images: describe concepts in detail, suggest composition, colors, mood, and provide ready-to-use prompts for image generation tools\n- For video: write scripts with shot lists, voiceover text, timing, and visual direction\n- For audio/podcasts: outline segments, suggest interview questions, and describe sound design\n- Be specific and actionable — the user should be able to hand your output to a designer or creator\n- If asked about tools: recommend appropriate tools (e.g., Midjourney, ElevenLabs, CapCut)",
        ];

        return $prompts[$agentType] ?? $prompts['social'];
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
