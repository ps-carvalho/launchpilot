<?php

declare(strict_types=1);

namespace App\Dashboard\Service;

use Marko\Database\Query\QueryBuilderFactoryInterface;

/**
 * Resolves the model configuration for a given output modality.
 * Free tier uses OpenRouter zero-cost or low-cost models per modality.
 * Pro tier can override per modality via user_settings.agent_models JSON.
 */
class AgentModelResolver
{
    /** @var array<string, array{model: string, temperature: float, max_tokens: int}> */
    private const FREE_DEFAULTS = [
        'text' => [
            'model' => 'meta-llama/llama-3.3-70b-instruct',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
        'image' => [
            'model' => 'black-forest-labs/flux-2-schnell',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
        'video' => [
            'model' => 'google/veo-3.1-lite',
            'temperature' => 0.7,
            'max_tokens' => 2000,
        ],
    ];

    /** @var array<string, array<int, array{value: string, label: string}>> */
    private const MODALITY_MODELS = [
        'text' => [
            ['value' => 'meta-llama/llama-3.3-70b-instruct', 'label' => 'Llama 3.3 70B (Creative, Free)'],
            ['value' => 'deepseek/deepseek-chat', 'label' => 'DeepSeek Chat (Reasoning, Free)'],
            ['value' => 'nvidia/llama-3.1-nemotron-70b-instruct', 'label' => 'Nemotron 70B (Instruction, Free)'],
            ['value' => 'qwen/qwen-2.5-72b-instruct', 'label' => 'Qwen 2.5 72B (Multilingual, Free)'],
            ['value' => 'openai/gpt-4o-mini', 'label' => 'GPT-4o Mini (BYOK / Paid)'],
        ],
        'image' => [
            ['value' => 'black-forest-labs/flux-2-pro', 'label' => 'FLUX.2 Pro (Highest Quality)'],
            ['value' => 'black-forest-labs/flux-2-flex', 'label' => 'FLUX.2 Flex (Balanced)'],
            ['value' => 'black-forest-labs/flux-2-schnell', 'label' => 'FLUX.2 Schnell (Fast, Free)'],
            ['value' => 'google/gemini-3.1-flash-image-preview', 'label' => 'Gemini 3.1 Flash Image (Fast)'],
            ['value' => 'openai/gpt-5-image-mini', 'label' => 'GPT-5 Image Mini (Efficient)'],
            ['value' => 'openai/gpt-5-image', 'label' => 'GPT-5 Image (Best Quality)'],
            ['value' => 'bytedance-seed/seedream-4.5', 'label' => 'Seedream 4.5 (Editing)'],
        ],
        'video' => [
            ['value' => 'kwaivgi/kling-v3.0-pro', 'label' => 'Kling v3.0 Pro (Best Quality)'],
            ['value' => 'kwaivgi/kling-v3.0-std', 'label' => 'Kling v3.0 Standard (Balanced)'],
            ['value' => 'google/veo-3.1-fast', 'label' => 'Veo 3.1 Fast (Speed)'],
            ['value' => 'google/veo-3.1-lite', 'label' => 'Veo 3.1 Lite (Cheapest)'],
            ['value' => 'alibaba/wan-2.7', 'label' => 'Wan 2.7 (Open Source)'],
            ['value' => 'bytedance/seedance-2.0', 'label' => 'Seedance 2.0 (Character Consistency)'],
        ],
    ];

    public function __construct(
        private readonly QueryBuilderFactoryInterface $queryFactory,
    ) {}

    /**
     * Resolve model configuration for an output modality.
     *
     * @return array{model: string, temperature: float, max_tokens: int}
     */
    public function resolve(int $userId, string $modality): array
    {
        $modality = $this->normalizeModality($modality);
        $defaults = self::FREE_DEFAULTS[$modality] ?? self::FREE_DEFAULTS['text'];

        $settings = $this->queryFactory->create()->table('user_settings')
            ->where('user_id', '=', $userId)
            ->first();

        if ($settings !== null && $settings['tier'] === 'pro' && !empty($settings['agent_models'])) {
            $overrides = json_decode($settings['agent_models'] ?? '{}', true) ?: [];
            if (!empty($overrides[$modality]['model'])) {
                return [
                    'model' => $overrides[$modality]['model'],
                    'temperature' => $overrides[$modality]['temperature'] ?? $defaults['temperature'],
                    'max_tokens' => $overrides[$modality]['max_tokens'] ?? $defaults['max_tokens'],
                ];
            }
        }

        return $defaults;
    }

    /**
     * Get available model options for a specific modality.
     *
     * @return array<int, array{value: string, label: string}>
     */
    public function modelsForModality(string $modality): array
    {
        return self::MODALITY_MODELS[$this->normalizeModality($modality)] ?? [];
    }

    /**
     * Get all available models grouped by modality.
     *
     * @return array<string, array<int, array{value: string, label: string}>>
     */
    public function availableModels(): array
    {
        return self::MODALITY_MODELS;
    }

    /**
     * Get defaults for all modalities.
     *
     * @return array<string, array{model: string, temperature: float, max_tokens: int}>
     */
    public function defaults(): array
    {
        return self::FREE_DEFAULTS;
    }

    /**
     * Get the user's per-modality model overrides (Pro only).
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

    private function normalizeModality(string $modality): string
    {
        // Map legacy agent types to text modality
        return match ($modality) {
            'social', 'content', 'seo', 'brainstorm', 'media' => 'text',
            default => $modality,
        };
    }
}
