# LaunchPilot MVP — Product Requirements Document

## Problem Statement

Solo founders and small business owners need to market their products but cannot afford expensive enterprise marketing suites (HubSpot, Buffer, Hootsuite, etc.). They lack marketing expertise, don't know where to start, and struggle to maintain consistent brand presence across channels. Existing tools are either too expensive, too complex, or not tailored to their specific business context.

## Solution

LaunchPilot is an AI-native marketing co-pilot built specifically for solo founders and small businesses. Users onboard by sharing their business context (website, documents, answers to questions). LaunchPilot builds a maintained knowledge base from this input and uses it to power specialized AI agents that generate marketing content, provide SEO guidance, brainstorm features, and suggest promotional strategies. All outputs are organized inside campaigns with a content workflow (draft → approved → scheduled → published).

The product is priced affordably: a generous free tier for getting started, and a premium tier for power users who need custom agents, unlimited usage, and advanced integrations.

## User Stories

### Onboarding & Knowledge Base

1. As a new user, I want to sign up with email and password, so that I can start using LaunchPilot immediately without social auth.
2. As a new user, I want LaunchPilot to auto-create a workspace for me on signup, so that I don't have to configure anything before starting.
3. As a new user, I want to paste my website URL during onboarding, so that LaunchPilot can scrape and understand my business automatically.
4. As a new user, I want to answer a few questions about my business (target audience, value proposition, etc.), so that the knowledge base captures context the website doesn't reveal.
5. As a user, I want to upload documents (PDFs, Word docs, text files) to my knowledge base, so that LaunchPilot can learn from my existing marketing materials, brand guidelines, and product documentation.
6. As a user, I want to view all documents in my knowledge base, so that I can see what context the AI is working from.
7. As a user, I want to delete or replace documents in my knowledge base, so that I can keep the AI's context accurate and up-to-date.
8. As a user, I want to export my knowledge base as a Markdown compilation, so that I can back it up or use it outside LaunchPilot.

### Campaigns

9. As a user, I want to create a campaign with a title and description, so that I can organize my marketing work around a specific initiative.
10. As a user, I want campaigns to support different types (one-off launch, recurring content stream, ongoing presence), so that I can model any marketing initiative.
11. As a user, I want to view all my campaigns in a list, so that I can see what's active, completed, or still in draft.
12. As a user, I want to archive old campaigns, so that my dashboard stays focused on current work.
13. As a user, I want to click into a campaign and see all generated content items inside it, so that I can review and manage outputs.
14. As a user, I want each content item to have a status (draft, approved, scheduled, published), so that I can track where it is in the workflow.
15. As a user, I want to edit generated content items before approving them, so that I can add my voice and fix any inaccuracies.
16. As a user, I want to copy approved content with one click, so that I can paste it into LinkedIn, Facebook, or my blog manually.
17. As a user, I want to mark content as "published" once I've posted it manually, so that my campaign dashboard reflects reality.

### Agents (Free Tier — Pre-built)

18. As a free-tier user, I want to run the **Content Agent**, so that it generates blog posts, success stories, and product announcements based on my knowledge base.
19. As a free-tier user, I want to run the **Social Agent**, so that it generates LinkedIn posts, Facebook posts, and promotional copy for my chosen platform.
20. As a free-tier user, I want to run the **SEO Agent**, so that it reviews my Google Search Console data and suggests keyword optimizations and content gaps.
21. As a free-tier user, I want to run the **Brainstorm Agent**, so that it suggests potential product features and gives advice on targeting my ideal customers.
22. As a free-tier user, I want each agent run to feel like a conversation, so that I can ask follow-up questions and refine outputs interactively.
23. As a free-tier user, I want my agent conversation sessions to be saved, so that I can refer back to past discussions and decisions.
24. As a free-tier user, I want agent outputs to be automatically saved as content items inside the active campaign, so that I don't lose generated work.
25. As a free-tier user, I want to see my daily agent run limit (10 runs/day), so that I understand my usage.

### Agents (Premium Tier — Custom)

26. As a premium user, I want to create custom agents with my own prompts, so that I can tailor AI behavior to highly specific tasks.
27. As a premium user, I want to bring my own OpenRouter API key, so that I control costs and get unlimited agent runs.
28. As a premium user, I want to create multiple workspaces (one per project), so that I can manage marketing for multiple products without context bleeding.
29. As a premium user, I want Google Search Console OAuth integration, so that the SEO Agent can pull real ranking data automatically.
30. As a premium user, I want higher or uncapped rate limits, so that I can run agents as frequently as needed.

### Publishing & Export

31. As a user, I want to copy any generated content to my clipboard, so that I can manually publish it anywhere.
32. As a user, I want to export a campaign's content as Markdown, so that I can share it with teammates or store it externally.
33. As a premium user (post-MVP), I want to connect my LinkedIn and Facebook accounts, so that LaunchPilot can publish content automatically.

### Workspace & Team (Post-MVP)

34. As a user, I want to invite teammates to my workspace, so that we can collaborate on campaigns.
35. As a workspace owner, I want to assign roles (owner, editor, viewer) to teammates, so that I can control who can run agents and edit content.

## Implementation Decisions

### Architecture

- **Workspace = Project for MVP**: Each user gets one workspace on signup. Multi-workspace is a premium feature. This keeps the data model simple while leaving room for expansion.
- **Campaign as Content Container**: Campaigns are flexible containers that hold content items. They can represent one-off launches, recurring streams, or hybrid initiatives. The campaign itself has minimal state; content items carry the workflow status.
- **Content Item Workflow**: Each content item has `type` (social_post, blog_post, seo_report, brainstorm_note), `platform` (linkedin, facebook, blog, null), `status` (draft, approved, scheduled, published), `content` (generated text), and optional `published_at`/`scheduled_at` timestamps.

### Knowledge Base & RAG

- **Vector DB**: Use `pgvector` extension in the existing PostgreSQL 16 container. No additional services required.
- **Embeddings**: Generated via OpenRouter using `openai/text-embedding-3-small` (~$0.02 per 1M tokens). Embeddings are 1536-dimensional vectors.
- **Chunking Strategy**: Fixed-size chunks of ~500 tokens with 50 token overlap. Simple and effective for MVP.
- **Schema**: Two tables — `knowledge_documents` (raw text, filename, metadata) and `knowledge_chunks` (chunk text, embedding vector, foreign key to document).
- **Retrieval**: Hybrid search combining vector similarity (cosine distance via pgvector) with keyword matching for robust RAG.

### Agents

- **Pre-built Agents (Free Tier)**: Four locked agents with tuned prompts:
  - **Content Agent**: Generates long-form content (blog posts, success stories, product announcements)
  - **Social Agent**: Generates short-form content for LinkedIn, Facebook, or generic promotions
  - **SEO Agent**: Analyzes Google Search Console data and suggests optimizations
  - **Brainstorm Agent**: Suggests features and gives targeting advice based on knowledge base
- **Custom Agents (Premium Tier)**: Users write their own system prompts. The agent framework uses the same RAG pipeline and OpenRouter integration.
- **Interaction Model**: Conversational. Each agent run opens a chat session. The user describes what they want, the agent responds with generated content, and the user can ask follow-ups. The full conversation thread is saved as an `agent_session`.
- **Output Persistence**: When the user is satisfied with a response, they can "save to campaign" which creates a `content_item` in the active campaign.

### OpenRouter Integration

- **Free Tier**: Uses LaunchPilot's shared OpenRouter API key. Rate limited to 10 agent runs per day.
- **Premium Tier**: User brings their own OpenRouter API key (BYOK). Unlimited runs.
- **Agent Execution**: Each message in a conversation counts as one "run" against the limit. The system prompt (including injected knowledge base context) is constructed server-side and sent to OpenRouter along with the user's message and conversation history.

### Publishing

- **MVP**: Copy-paste only. No OAuth integrations. Each content item has a "Copy to clipboard" action.
- **Post-MVP Premium**: OAuth integrations for LinkedIn and Facebook to enable one-click publishing.

### Pricing Tiers

- **Free**: 1 workspace, pre-built agents only (locked prompts), LaunchPilot's OpenRouter key, 10 agent runs/day, copy-paste publishing, Google Search Console integration.
- **Pro ($19/month)**: Unlimited workspaces, custom agents, BYOK OpenRouter, uncapped usage, knowledge base export, multi-project workspaces.

### Database Schema Additions

New migrations needed beyond the existing `users`, `workspaces`, `workspace_user`, `campaigns`:

- `content_items`: id, campaign_id, type, platform, status, content, metadata JSONB, published_at, scheduled_at, created_at, updated_at
- `agent_sessions`: id, campaign_id, agent_type, user_id, messages JSONB (array of {role, content, timestamp}), created_at, updated_at
- `knowledge_documents`: id, workspace_id, filename, original_name, mime_type, raw_text, metadata JSONB, created_at, updated_at
- `knowledge_chunks`: id, document_id, chunk_text, embedding vector(1536), chunk_index, created_at
- `user_settings`: id, user_id, openrouter_api_key (encrypted), tier (free/pro), daily_runs_used, runs_reset_at, gsc_refresh_token (encrypted)

### Modules to Build

- **OnboardingModule**: Handles website scraping, questionnaire flow, initial knowledge base seeding
- **KnowledgeBaseModule**: Document upload, chunking, embedding generation, vector search, export
- **AgentModule**: Agent execution via OpenRouter, conversation management, prompt templating, RAG context injection
- **CampaignModule**: Campaign CRUD, content item workflow, campaign-level organization
- **ContentModule**: Content item CRUD, status transitions, copy-to-clipboard, markdown export
- **SEOModule**: Google Search Console OAuth, data fetching, SEO report generation
- **BillingModule** (post-MVP): Subscription management, tier enforcement, usage tracking

## Testing Decisions

- **Agent tests**: Mock OpenRouter API responses. Test that the prompt construction correctly injects knowledge base context. Test conversation history formatting. Do NOT test the LLM's creativity — test the integration.
- **RAG tests**: Test chunking logic produces expected chunk sizes and overlap. Test vector search returns relevant chunks for sample queries. Use a test document with known content.
- **Campaign workflow tests**: Test content item status transitions (draft → approved → published). Test that unauthorized transitions are rejected.
- **Rate limiting tests**: Test that free-tier users are blocked after 10 runs. Test that the counter resets daily.
- **Knowledge base export tests**: Test that export produces valid Markdown containing all documents and generated content.

## Out of Scope

- Email verification and password reset (MVP uses minimal auth)
- Social login (Google, GitHub, etc.)
- Auto-publishing to LinkedIn/Facebook (MVP is copy-paste only)
- Team collaboration and role-based access (post-MVP)
- Multi-project workspaces (premium post-MVP feature)
- Real-time notifications or webhooks
- Mobile app
- White-labeling or custom domains
- Analytics dashboard beyond basic campaign status
- A/B testing of generated content
- Image/video generation

## Further Notes

- **SEO Agent Data Source**: Google Search Console integration requires OAuth flow with Google. For MVP, this is available on the free tier as a differentiator. The user must connect their GSC account before the SEO Agent can provide real data.
- **Brainstorm Agent Limitations**: The "target potential customers" feature outputs generic advice based on the knowledge base only. It does NOT scrape LinkedIn or search the web for actual leads in the MVP.
- **Session Storage**: Agent sessions store the full message array as JSONB. This is simple and queryable for MVP. If conversation volumes grow significantly, consider normalizing to a separate messages table.
- **Knowledge Base Improvement**: The knowledge base is passive in MVP — users add/remove documents. Future iterations could include automatic re-scraping of the user's website, suggested document additions, or knowledge gap detection.
- **Privacy**: User documents and generated content belong to the workspace. LaunchPilot does not use customer data to train models. OpenRouter usage is subject to their privacy policy.
- **Rate Limit Reset**: Daily run counters reset at midnight UTC. Consider timezone-aware reset for better UX in future iterations.
