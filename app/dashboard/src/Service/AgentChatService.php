<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

class AgentChatService
{
    private string $apiKey;

    public function __construct()
    {
        $this->apiKey = $_ENV['OPENROUTER_API_KEY'] ?? '';
    }

    /**
     * Send a message to the agent and get a response.
     *
     * @param array<int, array{role: string, content: string}> $history
     * @param array<int, array<string, mixed>> $kbContext
     * @return array{role: string, content: string}|null
     */
    public function chat(string $agentType, string $userMessage, array $history, array $kbContext): ?array
    {
        if (empty($this->apiKey) || $this->apiKey === 'sk-or-v1-REPLACE_ME') {
            return [
                'role' => 'assistant',
                'content' => "⚠️ OpenRouter API key is not configured. Please add `OPENROUTER_API_KEY` to your `.env` file to enable AI agents.",
            ];
        }

        $systemPrompt = $this->buildSystemPrompt($agentType, $kbContext);

        $messages = [
            ['role' => 'system', 'content' => $systemPrompt],
        ];

        foreach ($history as $msg) {
            $messages[] = $msg;
        }

        $messages[] = ['role' => 'user', 'content' => $userMessage];

        $response = $this->request([
            'model' => 'openai/gpt-4o-mini',
            'messages' => $messages,
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ]);

        if ($response === null) {
            return null;
        }

        $content = $response['choices'][0]['message']['content'] ?? null;

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
    private function buildSystemPrompt(string $agentType, array $kbContext): string
    {
        $kbText = '';
        foreach ($kbContext as $chunk) {
            $kbText .= "\n---\nSource: {$chunk['original_name']}\n";
            $kbText .= $chunk['chunk_text'];
        }

        $prompts = [
            'social' => "You are a Social Media Marketing Agent for LaunchPilot. Your job is to write engaging, platform-appropriate social media posts based on the user's business context.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Write short, punchy, engaging posts\n- Match the tone to the platform (LinkedIn = professional, Facebook = friendly, general = adaptable)\n- Include relevant hashtags when appropriate\n- Always provide the post text ready to copy-paste\n- If the user asks for multiple posts, number them clearly",

            'content' => "You are a Content Marketing Agent for LaunchPilot. Your job is to write long-form marketing content like blog posts, success stories, and product announcements based on the user's business context.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Write compelling, well-structured long-form content\n- Use headings, bullet points, and paragraphs for readability\n- Include a catchy title and conclusion\n- Optimize for SEO naturally (no keyword stuffing)\n- Match the tone to the audience described in the business context",

            'seo' => "You are an SEO Agent for LaunchPilot. Your job is to analyze websites and provide actionable SEO recommendations.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Provide specific, actionable SEO advice\n- Suggest keywords to target based on the business context\n- Recommend content gaps to fill\n- Give concrete steps the user can take to improve their search rankings\n- Be specific, not generic",

            'brainstorm' => "You are a Product Strategy Agent for LaunchPilot. Your job is to help founders brainstorm product features and identify their target customers.\n\nBUSINESS CONTEXT:\n{$kbText}\n\nInstructions:\n- Suggest potential features that would add value\n- Identify the ideal customer profile based on the context\n- Provide actionable advice on positioning and messaging\n- Be creative but grounded in the business context provided",
        ];

        return $prompts[$agentType] ?? $prompts['social'];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function request(array $payload): ?array
    {
        $json = json_encode($payload);
        if ($json === false) {
            return null;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $this->apiKey,
                    'HTTP-Referer: https://launchpilot.ai',
                    'X-Title: LaunchPilot',
                ],
                'content' => $json,
                'timeout' => 60,
            ],
        ]);

        $response = @file_get_contents('https://openrouter.ai/api/v1/chat/completions', false, $context);

        if ($response === false) {
            return null;
        }

        $decoded = json_decode($response, true);

        return is_array($decoded) ? $decoded : null;
    }
}
