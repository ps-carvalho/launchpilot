# PRD — Agent Differentiation v2.0

## Problem Statement

The 4 AI agents in LaunchPilot (social, content, seo, brainstorm) are indistinguishable to users beyond their labels. They all use the same model, same temperature, same max tokens, and the only difference is a system prompt. Users can't tell what each agent is actually *for* beyond the name. The content and brainstorm agents overlap significantly — both generate text and ideas. There is no way to plan media (images, video, audio). Free-tier users have no visibility into which OpenRouter model they're using. Generated content items cannot be deleted, cluttering campaigns. Pro users who BYOK cannot choose different models per agent.

## Solution

Redesign the agent system with **genuine differentiation**: each agent gets a distinct model, distinct capabilities, distinct output format, and distinct tool access. Merge content and brainstorm into a single unified Content Strategist agent. Introduce a Media agent for image/video/audio planning (text-only for free, actual generation for Pro). Lock the free tier to OpenRouter's zero-cost models. Add soft-delete for content items. Give Pro users per-agent model selection with quick-switch UI.

## User Stories

1. As a free-tier user, I want each agent to feel genuinely different, so that I know which one to pick for my task.
2. As a free-tier user, I want the Social agent to write short, platform-aware posts quickly, so that I can populate my calendar without waiting.
3. As a free-tier user, I want the Content agent to handle both blog writing and strategic brainstorming, so that I don't have to switch between two similar agents.
4. As a free-tier user, I want the SEO agent to analyze my content and suggest improvements with structured data, so that I can improve rankings.
5. As a free-tier user, I want the Media agent to suggest image concepts, video scripts, and audio descriptions, so that I can brief a designer or creator.
6. As a free-tier user, I want to know which AI model is answering me, so that I understand the quality/limitations of the response.
7. As a free-tier user, I want to delete content items I no longer need, so that my campaign stays clean.
8. As a Pro user, I want to choose a different model per agent, so that I can optimize for speed vs. quality per task.
9. As a Pro user, I want to switch models quickly inside the agent chat, so that I can experiment without leaving the campaign.
10. As a Pro user, I want the Media agent to generate actual images, so that I can produce creative assets without external tools.
11. As a Pro user, I want custom prompts to still work with the new merged Content agent, so that my existing customizations aren't lost.
12. As a developer, I want the agent system to be extensible via the ContextBuilderRegistry, so that adding a 5th agent requires only registering a new builder.
13. As a developer, I want soft-deleted content items to remain in the database, so that an "undo" or "recently deleted" feature is possible later.
14. As a developer, I want the free-tier model lineup to be configuration-driven, so that when OpenRouter rotates free models we can update without code changes.
15. As a user, I want deleted content items to disappear immediately from the UI, so that I don't see clutter.

## Implementation Decisions

### Agent Architecture

**Merged Agent: Content**
- Replaces both `content` and `brainstorm` agent types
- Unified persona: "Content Strategist" — writes long-form AND brainstorms strategy
- Detects mode from user message intent (no manual switching)
- Model: `meta-llama/llama-3.3-70b-instruct` (free tier) — better at creative writing than 8B

**New Agent: Media**
- Free tier: Text-only media strategist
  - Suggests image concepts with detailed prompts ready for external tools
  - Writes video scripts (shot lists, voiceover text, timing)
  - Describes audio/podcast segments
- Pro tier: Actual image generation via separate provider integration (e.g., Pollinations.ai or Stability AI free tier)
- Model: `meta-llama/llama-3.2-11b-vision-instruct` (free tier) — vision-capable for image-aware strategy

**Retained Agents: Social, SEO**
- Social: Fast, punchy, platform-aware. Model: `meta-llama/llama-3.1-8b-instruct`
- SEO: Structured analysis with GSC enrichment. Model: `deepseek/deepseek-chat`

### Model Configuration

**Free Tier (OpenRouter zero-cost models):**
- Default lineup stored in database/config, not hardcoded
- Admin can rotate models via env or settings without deployment
- Model IDs displayed in agent tab header as small text (e.g., "· llama-3.1-8b")

**Pro Tier:**
- Per-agent model selector in Settings page
- Quick-switch dropdown in each agent tab header
- BYOK still works as fallback
- Model options populated from a curated allowlist of OpenRouter models

### AgentChatService Refactor

- Accept `model` parameter in `chat()` method
- Default model resolved per agent type via `AgentModelResolver` service
- Temperature and max_tokens also configurable per agent
- Social: temp 0.8 (creative), max 800 tokens (short posts)
- Content: temp 0.7, max 2000 tokens (long-form)
- SEO: temp 0.3 (factual), max 1500 tokens (structured)
- Media: temp 0.7, max 2000 tokens

### Content Item Soft Delete

- Add `deleted_at` nullable timestamp to `content_items` table
- Migration: `ALTER TABLE content_items ADD COLUMN deleted_at TIMESTAMP NULL`
- `ContentItemGate::itemsForCampaign()` filters `WHERE deleted_at IS NULL`
- New endpoint: `POST /api/content-items/{id}/delete`
- UI: "Delete" button on content item cards with confirmation
- Immediate removal from UI; item preserved in DB

### ContextBuilderRegistry Update

- Media agent context builder: `MediaContextBuilder`
  - Scans knowledge base for brand guidelines, product descriptions
  - Suggests visual style from uploaded documents
  - Free tier: no external API calls
  - Pro tier: may call image analysis APIs if vision model is available

### UI Changes

- Campaign detail: 4 tabs → Social, Content, SEO, Media
- Content tab label changes from "Content Agent" to "Content Strategist"
- Each tab header shows active model name in muted text
- Pro users see small model dropdown in tab header
- Content items gain "Delete" action alongside Copy/Edit/Status

## Testing Decisions

- **Agent differentiation tests** — Assert that each agent type sends a different model ID to OpenRouter. Mock the HTTP client and verify the request body contains the expected model.
- **Merged content agent tests** — Assert that both "write a blog" and "brainstorm ideas" messages succeed with the same agent type. Verify the system prompt contains both writing and strategy instructions.
- **Media agent tests** — Free tier: assert response contains text recommendations, no image URLs. Pro tier: assert image generation service is called.
- **Soft delete tests** — Assert deleted items are hidden from `itemsForCampaign()`. Assert `deleted_at` is set. Assert non-deleted items are still visible.
- **Model configuration tests** — Assert `AgentModelResolver` returns correct model per agent type and tier. Assert Pro users can override.
- **Prior art:** `AgentPipelineTest` for chat flow, `AgentControllerTest` for endpoints, `ContentItemControllerTest` for status transitions.

## Out of Scope

- Actual video/audio generation (even for Pro) — text scripts only
- Image generation for free tier
- Undo/restore for deleted content items (schema supports it, UI deferred)
- Automatic model rotation/fallback if OpenRouter free model goes offline
- Agent-to-agent handoff (e.g., "write this, then SEO-optimize it")
- Real-time streaming responses

## Further Notes

- The merged Content agent's custom prompt field should be backward-compatible: existing `content` and `brainstorm` custom prompts in `user_settings.custom_prompts` should be concatenated into the new `content` prompt on first load after migration.
- OpenRouter free models change frequently. The `AgentModelResolver` should read from the database first (for admin overrides), then env vars, then hardcoded defaults.
- The Media agent's image generation for Pro should be a separate service (`ImageGenerationService`) not mixed into `AgentChatService`, keeping the chat seam clean.
- Consider adding a `model` column to `agent_sessions` so users can see which model generated each conversation.
