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
| **Inertia.js Integration** | React 19 SPA with Vite HMR and shared data propagation |
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
- **AI Agent Chat** — 4 modalities: text, image, video, and audio generation with session persistence
- **Knowledge Base** — Upload TXT, MD, PDF, DOCX; scrape websites via URL; automatic chunking; pgvector similarity search
- **Content Workflow** — Draft → Approved → Scheduled → Published status transitions
- **GSC Integration** — Google Search Console OAuth for SEO agent enrichment
- **Tier System** — Free (10 runs/day) and Pro (unlimited) with BYOK support
- **Background Jobs** — Document embedding processed asynchronously via queue worker
- **Media Assets** — Download and delete generated images, videos, and audio clips

## Tech Stack

| Layer | Technology |
|---|---|
| Framework | Marko PHP 0.5.0 |
| Frontend | React 19 + Inertia.js + Tailwind CSS v4 + Vite |
| Database | PostgreSQL 16 with pgvector extension |
| Queue | Database-backed (`marko/queue-database`) with Docker worker |
| Cache | Redis (`marko/cache-redis`) |
| Sessions | Database-backed (`marko/session-database`) — local path package |
| Logging | File-based (`marko/log-file`) |
| Filesystem | Local (`marko/filesystem-local`) |
| LLM | OpenRouter API (Gemini, Llama, Veo, Sesame models) |
| Auth | Session-based (`marko/authentication`) |
| Validation | `marko/validation` with 422 error responses |
| Testing | Pest PHP 4.0 + Playwright E2E |

## Quick Start — Active Development

The recommended workflow for day-to-day development uses Docker Compose with volume mounts for live reloading.

### 1. Build frontend assets

```bash
npm install
npm run build
```

Frontend assets must be built on the host first so the `public/build` directory exists when containers start.

### 2. Start services

```bash
docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d --build
```

This starts:
- `postgres` — PostgreSQL 16 with pgvector
- `redis` — Redis 7 (cache)
- `app` — PHP-FPM with live code mounts (`./app`, `./config`, `./modules`, `./public`, `./storage`)
- `worker` — Queue worker with live code mounts
- `nginx` — Reverse proxy with `./public` mounted for static assets

### 3. Run migrations

```bash
docker compose exec app php vendor/bin/marko db:migrate
```

### 4. Access the app

- **App:** http://localhost:8080
- **Default login:** `admin@spavn.dev` / `password`

### 5. Optional: Vite HMR

For hot module replacement during frontend development:

```bash
# In a separate terminal
npm run dev
```

Then set `VITE_USE_DEV_SERVER: "true"` in `docker-compose.dev.yml` and restart the app container.

> ⚠️ **Do not leave `VITE_USE_DEV_SERVER=true` permanently.** If the Vite dev server is not running on your host, the dashboard will show a white screen because asset URLs point to `http://host.docker.internal:5173`.

---

### Production-like Build (No Live Reload)

```bash
# Build and start all services from images (no volume mounts)
docker compose up -d --build

# Run migrations
docker compose exec app php vendor/bin/marko db:migrate

# App is available at http://localhost:8080
```

All three targets (`app`, `worker`, `nginx`) are built together from the same base so asset hashes stay in sync. The Dockerfile purges stale `public/build` artifacts before each build to prevent old hashed files from leaking into new images.

### Local Development — No Docker (except PostgreSQL)

```bash
# Install dependencies
composer install
npm install

# Build assets
npm run build

# Start PostgreSQL (Docker)
docker compose up -d postgres redis

# Run migrations
php vendor/bin/marko db:migrate

# Start PHP dev server
php vendor/bin/marko up   # localhost:8000
```

## Queue Worker

The worker processes background jobs (e.g., document chunking + embedding) from the database queue:

```bash
# Docker (runs automatically with docker compose up -d)
docker compose up -d worker

# Or run directly for debugging
docker compose exec worker php vendor/bin/marko queue:work

# Process one job and exit
docker compose exec worker php vendor/bin/marko queue:work --once
```

Jobs are dispatched automatically when you upload documents or scrape URLs. The worker requires `OPENROUTER_API_KEY` to generate embeddings.

## Testing

```bash
# PHP unit + integration tests
php vendor/bin/pest

# E2E tests with Playwright
npm run test:e2e

# Build for production
npm run build
```

## Environment Variables

Key variables in `.env`:

| Variable | Description | Default |
|---|---|---|
| `APP_ENV` | Application environment | `local` |
| `APP_KEY` | Encryption key for sessions/security | *(required)* |
| `DB_CONNECTION` / `DB_HOST` / `DB_DATABASE` | PostgreSQL settings | `pgsql` / `postgres` / `launchpilot` |
| `OPENROUTER_API_KEY` | API key for OpenRouter LLM access | *(required for AI features)* |
| `SESSION_DRIVER` | Session storage driver | `database` |
| `CACHE_DRIVER` | Cache driver | `redis` |
| `QUEUE_DRIVER` | Queue driver | `database` |
| `VITE_USE_DEV_SERVER` | Use Vite dev server for HMR | `false` |

## Collaborate

This project exists to push Marko PHP forward. Contributions in any form are welcome:

- **Bug reports** — Open an issue with reproduction steps
- **Feature ideas** — Check existing PRDs in `docs/` or propose new ones
- **Refactors** — The codebase follows a shadow-depth design system; deep architectural improvements are especially valued
- **Documentation** — Marko needs more real-world examples

## License

MIT
