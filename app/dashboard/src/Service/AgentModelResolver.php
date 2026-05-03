<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Resolves the model, temperature, and max_tokens for a given agent type.
 * Free tier uses OpenRouter zero-cost models.
 * Pro tier can override per agent via user_settings.agent_models JSON.
 */
class AgentModelResolver
{
    /** @var array<string, array{model: string, temperature: float, max_tokens: int}> */
    private const FREE_DEFAULTS = [
        'social' => [
            'model' => 'meta-llama/llama-3.1-8b-instruct',
            'temperature' => 0.8,
            'max_tokens' => 800,
        ],
        'content' => [
            'model' => 'meta-llama/llama-3.3-70b-instruct',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
        'seo' => [
            'model' => 'deepseek/deepseek-chat',
            'temperature' => 0.3,
            'max_tokens' => 1500,
        ],
        'media' => [
            'model' => 'meta-llama/llama-3.2-11b-vision-instruct',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
    ];

    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    /**
     * Resolve model configuration for an agent type.
     *
     * @return array{model: string, temperature: float, max_tokens: int}
     */
    public function resolve(int $userId, string $agentType): array
    {
        $agentType = $this->normalizeType($agentType);
        $defaults = self::FREE_DEFAULTS[$agentType] ?? self::FREE_DEFAULTS['social'];

        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if ($settings !== null && $settings['tier'] === 'pro' && !empty($settings['agent_models'])) {
            $overrides = json_decode($settings['agent_models'] ?? '{}', true) ?: [];
            if (!empty($overrides[$agentType]['model'])) {
                return [
                    'model' => $overrides[$agentType]['model'],
                    'temperature' => $overrides[$agentType]['temperature'] ?? $defaults['temperature'],
                    'max_tokens' => $overrides[$agentType]['max_tokens'] ?? $defaults['max_tokens'],
                ];
            }
        }

        return $defaults;
    }

    /**
     * Get all available model options for the Pro tier model selector.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function availableModels(): array
    {
        return [
            ['value' => 'meta-llama/llama-3.1-8b-instruct', 'label' => 'Llama 3.1 8B (Fast, Free)'],
            ['value' => 'meta-llama/llama-3.3-70b-instruct', 'label' => 'Llama 3.3 70B (Creative, Free)'],
            ['value' => 'deepseek/deepseek-chat', 'label' => 'DeepSeek Chat (Reasoning, Free)'],
            ['value' => 'meta-llama/llama-3.2-11b-vision-instruct', 'label' => 'Llama 3.2 11B Vision (Multimodal, Free)'],
            ['value' => 'nvidia/llama-3.1-nemotron-70b-instruct', 'label' => 'Nemotron 70B (Instruction, Free)'],
            ['value' => 'qwen/qwen-2.5-72b-instruct', 'label' => 'Qwen 2.5 72B (Multilingual, Free)'],
            ['value' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o Mini (BYOK / Paid)'],
        ];
    }

    /**
     * Get defaults for all agents (used in Settings UI).
     *
     * @return array<string, array{model: string, temperature: float, max_tokens: int}>
     */
    public function defaults(): array
    {
        return self::FREE_DEFAULTS;
    }

    /**
     * Get the user's per-agent model overrides (Pro only).
     *
     * @return array<string, mixed>
     */
    public function getUserModels(int $userId): array
    {
        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if ($settings === null || $settings['tier'] !== 'pro') {
            return [];
        }

        return json_decode($settings['agent_models'] ?? '{}', true) ?: [];
    }

    private function normalizeType(string $type): string
    {
        // Map legacy 'brainstorm' to merged 'content'
        return match ($type) {
            'brainstorm' => 'content',
            default => $type,
        };
    }
}
