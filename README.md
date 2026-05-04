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
| **Attribute Routing** | PHP 8 `#[Get('/campaigns')]` and `#[Post('/api/...')]` attributes across 10+ controllers |
| **DI Container** | Constructor auto-wiring for 30+ services; custom module bindings for context builder registry |
| **Middleware Pipeline** | Session → Auth → Inertia middleware chain on every dashboard route |
| **Inertia.js Integration** | React 19 SPA with SSR support, Vite HMR, and shared data propagation |
| **Module System** | Three modules (`app/user`, `app/web`, `app/dashboard`) with independent bindings |
| **PostgreSQL + pgvector** | Vector similarity search for RAG-powered AI agents |
| **Queue System** | Database-backed queue with dedicated worker container for background jobs |
| **Validation** | `marko/validation` integrated across all controllers with 422 error responses |

## Architecture Highlights

```
app/
├── user/             # Authentication module — UserProvider, login/register/logout
├── web/              # Web module — public pages, bindings (HTTP client, worker)
└── dashboard/        # Dashboard module — core business logic
    ├── Controller/       # 10+ HTTP controllers using attribute routing
    ├── Gate/             # Resource authorization (CampaignGate, KnowledgeBaseGate, ContentItemGate)
    ├── Job/              # Queue jobs (ProcessDocumentJob)
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
- **Queue worker for heavy lifting** — Document processing (chunking + embedding) runs async via `ProcessDocumentJob`

## Features

- **Campaign Management** — Create, edit, archive, and export marketing campaigns
- **AI Agent Chat** — 4 agent types (Social, Content, SEO, Brainstorm) with session persistence
- **Knowledge Base** — Upload TXT, MD, PDF, DOCX; scrape websites via URL; automatic chunking; pgvector similarity search
- **Content Workflow** — Draft → Approved → Scheduled → Published status transitions
- **GSC Integration** — Google Search Console OAuth for SEO agent enrichment
- **Tier System** — Free (10 runs/day) and Pro (unlimited) with BYOK support
- **Background Jobs** — Document embedding processed asynchronously via queue worker

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Marko PHP 0.5.0 |
| Frontend | React 19 + Inertia.js + Tailwind CSS v4 + Vite |
| Database | PostgreSQL 16 with pgvector extension |
| Queue | Database-backed (`marko/queue-database`) with Docker worker |
| Cache | File-based (`marko/cache-file`) |
| Logging | File-based (`marko/log-file`) |
| Filesystem | Local (`marko/filesystem-local`) |
| LLM | OpenRouter API (GPT-4o Mini default) |
| Auth | Session-based (`marko/authentication`) |
| Validation | `marko/validation` with 422 error responses |
| Testing | Pest PHP 4.0 + Playwright E2E |

## Getting Started

### Docker (Recommended)

```bash
# Build and start all services
docker compose up -d --build

# Run migrations
./vendor/bin/marko migrate

# The app is available at http://localhost:8080
```

### Local Development

```bash
# Install dependencies
composer install
npm install

# Start PostgreSQL (Docker)
docker compose up -d postgres

# Run migrations
./vendor/bin/marko migrate

# Start dev servers
./vendor/bin/marko up   # PHP on localhost:8000
npm run dev             # Vite on localhost:5173
```

## Queue Worker

The worker processes background jobs (e.g., document embedding) from the database queue:

```bash
# Run manually
docker compose up -d worker

# Or run directly
php vendor/bin/marko queue:work
```

## Testing

```bash
# PHP unit + integration tests (201 tests, 370+ assertions)
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
