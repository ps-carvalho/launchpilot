# LaunchPilot AI — Marko Framework Product Demo

> ⚠️ **Work in Progress** — This project is under active development. Features, APIs, and architecture are evolving rapidly. Not yet ready for production use.
>
> **A production-grade AI marketing platform built to showcase the [Marko PHP Framework](https://marko.build).**
>
> **Collaborations welcome!** If you're exploring Marko, want to contribute, or are building something similar, open an issue or PR.

LaunchPilot is an AI-driven marketing campaign planner for small businesses and entrepreneurs. It demonstrates how Marko PHP powers sophisticated full-stack applications — from attribute-driven routing and DI container auto-wiring to Inertia.js React frontends with Tailwind CSS.

## What It Demonstrates

| Marko Feature | How LaunchPilot Uses It |
|---|---|
| **Attribute Routing** | PHP 8 `#[Get('/campaigns')]` and `#[Post('/api/...')]` attributes across 8 controllers |
| **DI Container** | Constructor auto-wiring for 25+ services; custom module bindings for context builder registry |
| **Middleware Pipeline** | Session → Auth → Inertia middleware chain on every dashboard route |
| **Inertia.js Integration** | React 19 SPA with SSR support, Vite HMR, and shared data propagation |
| **Module System** | Dashboard module (`app/dashboard`) + Web module (`app/web`) with independent bindings |
| **PostgreSQL + pgvector** | Vector similarity search for RAG-powered AI agents |

## Architecture Highlights

```
app/dashboard/src/
├── Context/          # Request-scoped value objects (UserContext)
├── Controller/       # 8 HTTP controllers using attribute routing
├── Gate/             # Resource authorization (CampaignGate, KnowledgeBaseGate, ContentItemGate)
├── Pipeline/         # AgentPipeline with pluggable ContextBuilderRegistry
├── Repository/       # KnowledgeBaseRepository — deep seam over pgvector
├── Service/          # Split services: UsageQuota, ApiKeyResolver, AgentPromptRegistry
├── Flow/             # OnboardingFlow — business rule orchestration
└── Context/Builder/  # Pluggable agent context builders (KB, GSC)
```

### Design Decisions

- **Gates over raw authorization queries** — `CampaignGate::forUser()` centralizes ownership checks
- **Context builders over private pipeline methods** — Adding a new agent context source means registering a builder, not editing the pipeline
- **Split services over god services** — `UserSettingsService` facade delegates to `UsageQuota`, `ApiKeyResolver`, `AgentPromptRegistry`
- **Repository over shallow service** — `KnowledgeBaseRepository` replaces `VectorSearchService` with a richer query seam

## Features

- **Campaign Management** — Create, edit, archive, and export marketing campaigns
- **AI Agent Chat** — 4 agent types (Social, Content, SEO, Brainstorm) with session persistence
- **Knowledge Base** — Upload TXT/PDF/DOCX, automatic chunking, pgvector similarity search
- **Content Workflow** — Draft → Approved → Scheduled → Published status transitions
- **GSC Integration** — Google Search Console OAuth for SEO agent enrichment
- **Tier System** — Free (10 runs/day) and Pro (unlimited) with BYOK support

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Marko PHP 0.5.0 |
| Frontend | React 19 + Inertia.js + Tailwind CSS v4 + Vite |
| Database | PostgreSQL 16 with pgvector extension |
| LLM | OpenRouter API (GPT-4o Mini default) |
| Auth | Session-based (Marko Authentication) |
| Testing | Pest PHP 4.0 + Playwright E2E |

## Getting Started

```bash
# Install dependencies
composer install
npm install

# Start PostgreSQL (Docker)
docker compose up -d

# Run migrations
./vendor/bin/marko migrate

# Start dev servers
./vendor/bin/marko up   # PHP on localhost:8000
npm run dev             # Vite on localhost:5173
```

## Testing

```bash
# PHP unit + integration tests (201 tests, 370 assertions)
php vendor/bin/pest

# E2E tests with Playwright
npm run test:e2e

# Build for production
npm run build
```

## Collaborate

This project exists to push Marko PHP forward. Contributions in any form are welcome:

- **Bug reports** — Open an issue with reproduction steps
- **Feature ideas** — Check existing PRDs in `docs/` or propose new ones
- **Refactors** — The codebase follows a shadow-depth design system; deep architectural improvements are especially valued
- **Documentation** — Marko needs more real-world examples

## License

MIT
