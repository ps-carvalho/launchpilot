# PRD — Framework Showcase: Logging, SSE, Cache, Queue, Filesystem

## Problem Statement

LaunchPilot currently uses only ~45% of the available Marko framework packages. The codebase has **zero logging** — 36 throw/catch sites across services with no persistent trace, making production debugging impossible. The frontend uses manual `setTimeout` polling for video generation status, which is inefficient and brittle. Services perform expensive operations (document embedding, API calls, user settings lookups) on every request with no caching layer. Document chunking + vectorization and video downloads block the HTTP request thread. Raw `mkdir`/`file_put_contents` calls are scattered through media handling instead of a storage abstraction.

The project exists to showcase Marko PHP. Right now it only demonstrates the "basic" packages (routing, auth, database, inertia). The advanced packages — logging, real-time streaming, caching, background jobs, and storage abstraction — are completely absent, leaving a significant gap in the demo story.

## Solution

Install and integrate five Marko packages to create a production-grade foundation and demonstrate the framework's depth:

1. **`marko/log` + `marko/log-file`** — Structured logging with daily rotation, PSR-3 interface
2. **`marko/sse`** — Server-Sent Events for real-time video status updates and streaming AI chat
3. **`marko/cache` + `marko/cache-file`** — TTL-based caching for model configs, user settings, and GSC data
4. **`marko/queue` + `marko/queue-database`** — Background job processing using existing PostgreSQL
5. **`marko/filesystem` + `marko/filesystem-local`** — Storage abstraction replacing raw disk operations

## User Stories

### Logging

1. As a developer, I want structured logs for every agent request, so that I can debug production issues without reproducing them locally.
2. As a developer, I want failed OpenRouter API calls to be logged with HTTP status and response body, so that I know whether the issue is rate limiting, auth, or model unavailability.
3. As a developer, I want video generation lifecycle events (submit → poll → download → ready/failed) to be traceable in logs, so that I can diagnose stuck jobs.
4. As a developer, I want log files rotated daily, so that disk usage doesn't grow unbounded.
5. As a developer, I want `debug` level logs in local development and `info` in production, so that local debugging is verbose without flooding production.

### SSE (Server-Sent Events)

6. As a user generating a video, I want to see real-time status updates without manual page refreshes, so that I know when my video is ready.
7. As a user, I want the video status to push to my browser automatically, so that I don't need energy-wasting polling loops.
8. As a user chatting with the AI agent, I want to see the response appear word-by-word in real time, so that the experience feels responsive and modern.
9. As a user, I want the streaming chat to fall back to the current batch mode if my browser doesn't support EventSource, so that the feature degrades gracefully.
10. As a developer, I want SSE connections to be stateless and reconnect automatically, so that temporary network blips don't break the UX.

### Cache

11. As a user, I want agent model configurations to load instantly on repeat visits, so that the UI feels snappy.
12. As a user, I want my settings and tier information to be cached between requests, so that the dashboard doesn't query the database for data that rarely changes.
13. As a developer, I want cache entries to expire automatically after a TTL, so that stale data doesn't persist indefinitely.
14. As a developer, I want cache misses to be logged, so that I can tune TTL values based on actual hit rates.

### Queue

15. As a user uploading a knowledge base document, I want the UI to return immediately while chunking and embedding happen in the background, so that I don't wait 10-30 seconds for a large PDF.
16. As a user, I want video downloads to happen asynchronously after the generation completes, so that the poll response is fast even for large files.
17. As a developer, I want failed queue jobs to be retried with exponential backoff, so that transient OpenRouter errors don't permanently fail a video.
18. As a developer, I want queue workers to be runnable via a CLI command, so that I can scale background processing independently from web workers.
19. As a developer, I want queue jobs to use the existing PostgreSQL database, so that no additional infrastructure (Redis, RabbitMQ) is required.

### Filesystem

20. As a developer, I want all file operations to go through a storage abstraction, so that switching from local disk to S3 later requires zero code changes in services.
21. As a developer, I want media files to be written with consistent permissions and directory creation, so that permission errors don't cause intermittent failures.
22. As a developer, I want the filesystem abstraction to support streaming reads, so that large video files don't need to be loaded entirely into memory.

## Implementation Decisions

### Package Installation Order

The packages must be installed and wired in this order because each layer depends on the previous:

1. **Logging first** — every other package benefits from observability. Debug cache misses, queue failures, and SSE connection events.
2. **Filesystem second** — the queue and SSE packages may write temp files or media. Having the abstraction in place first prevents rework.
3. **Cache third** — cache sits in front of database queries and API calls. It needs logging to verify hit/miss rates.
4. **Queue fourth** — queue workers need logging, filesystem (for downloads), and may use cache for deduplication.
5. **SSE last** — SSE is the user-facing capstone. It touches the frontend, needs queue for background video polling, and uses logging for connection telemetry.

### Logging Architecture

- **Contract**: Inject `Marko\Log\Contracts\LoggerInterface` via constructor into all services
- **Config** (`config/log-file.php`): `path` → `storage/logs`, `channel` → `launchpilot`, `level` → `env('LOG_LEVEL', 'debug')`
- **Environment**: `LOG_LEVEL=debug` in `.env.example`, `LOG_LEVEL=info` in production docker-compose
- **What gets logged**:
  - `AgentPipeline::run` — `info` with user_id, modality, model
  - `AgentChatService::chat` — `debug` with token usage (if available), `error` on API failure with status code
  - `VideoGenerationService::submit` — `info` with job_id, model
  - `VideoGenerationService::poll` — `debug` with job_id, status
  - `VideoGenerationService::download` — `info` on success, `error` on failure with HTTP code
  - `ApiKeyResolver::resolve` — `warning` on fallback to env key
  - `UsageQuota::recordRun` — `debug` with user_id, remaining_runs
  - All `RuntimeException` throws in controllers — `error` with stack context

### SSE Architecture

Two independent SSE streams:

**Stream A: Video Status (`/api/media/{assetId}/stream`)**
- Backend: `SseStream` with `dataProvider` closure that polls `media_assets` table every 3 seconds
- Emits `status` events: `pending` → `processing` → `completed` / `failed`
- On `completed`, the event payload includes the local video URL so the frontend can switch to `<video>` immediately
- Frontend: `EventSource` in `MediaTab` component, auto-reconnect on disconnect
- Cleanup: Close EventSource when component unmounts

**Stream B: AI Chat Streaming (`/api/campaigns/{campaignId}/agents/{modality}/stream`)**
- Backend: `SseStream` with `dataProvider` that calls OpenRouter with `stream: true`
- Each token chunk from OpenRouter becomes a `token` SSE event
- Final `done` event signals completion and includes full metadata (model, usage, session_id)
- Frontend: `EventSource` in `AgentChat`, appends tokens to a growing message buffer
- Error handling: If streaming fails mid-response, fall back to the existing batch `/chat` endpoint

### Cache Architecture

- **Contract**: `Marko\Cache\Contracts\CacheInterface` injected where needed
- **What gets cached**:
  - `AgentModelResolver::resolve()` result — TTL 1 hour (model configs rarely change)
  - `UserSettingsService::forUser()` result — TTL 5 minutes (tier, daily_runs_used)
  - `ApiKeyResolver::resolve()` result — TTL 1 minute (env keys don't change, but BYOK might)
  - `CampaignGate::forUser()` result — TTL 10 minutes (campaign metadata is stable)
- **Cache keys**: Namespaced with `launchpilot.{user_id}.{key}` to prevent collisions
- **Invalidation**: Cache cleared on write operations (e.g. updating `user_settings` flushes the user settings cache)

### Queue Architecture

- **Backend**: `marko/queue-database` using the existing `launchpilot` PostgreSQL instance
- **Migration**: New table `queue_jobs` with standard columns (id, queue, payload, attempts, reserved_at, available_at, created_at)
- **Jobs to dispatch**:
  - `EmbedDocumentJob` — dispatched from `KnowledgeBaseController::upload()` after file upload succeeds. Replaces the current synchronous `DocumentParser → TextChunker → EmbeddingService` chain.
  - `DownloadVideoJob` — dispatched from `AgentController::pollVideo()` when status is `completed`. Replaces the synchronous `VideoGenerationService::download()` call inside the HTTP request.
  - `ExportCampaignJob` — dispatched from `CampaignController::export()`. Currently exports run synchronously and block for large campaigns.
- **Worker**: `./vendor/bin/marko queue:work` CLI command, run as a separate Docker service (`launchpilot-worker`) using the same `app` image but with a different CMD
- **Retries**: 3 attempts with exponential backoff (immediate, 30s, 2min). Failed jobs log `error` and update the relevant record status to `failed`.

### Filesystem Architecture

- **Contract**: `Marko\Filesystem\Contracts\FilesystemInterface` replaces all raw disk operations
- **Config** (`config/filesystem.php`): `default` → `local`, `disks.local.root` → `storage/media`
- **Replacements**:
  - `VideoGenerationService::download()` — `FilesystemInterface::write($path, $contents)` instead of `file_put_contents`
  - `KnowledgeBaseController::upload()` — `FilesystemInterface::write()` for temporary upload storage
  - `MediaController` (new or existing upload endpoint) — `FilesystemInterface::write()` for all uploads
- **Path generation**: Keep the existing convention `storage/media/{campaign_id}/{filename}` but generate paths through the filesystem contract
- **Future-proof**: Adding S3 later means only changing the `default` disk in config — zero service changes

### Docker Changes

- Add `launchpilot-worker` service to `docker-compose.yml`:
  - Same `app` image, overrides `CMD` to `./vendor/bin/marko queue:work`
  - Shares `app_storage` volume with the web container
  - No exposed ports
- Add `LOG_LEVEL=info` to the `app` service environment in `docker-compose.yml`

### Frontend Changes

- **`MediaTab.jsx`** — Replace `setTimeout(pollVideo, 5000)` loop with `EventSource`
- **`AgentChat.jsx`** — Add streaming mode: when user submits, try SSE first. If the browser doesn't support EventSource or the endpoint returns non-200, fall back to existing `fetch('/chat')`.
- **`Show.jsx`** — Add `EventSource` cleanup in `useEffect` return to prevent memory leaks.

## Testing Decisions

### What makes a good test

Only test external behavior and observable side effects, not implementation details. For logging: test that a failed API call results in a log file entry, not that `LoggerInterface::error()` was called with specific arguments. For SSE: test that the endpoint returns `text/event-stream` headers and emits events in the expected sequence. For cache: test that a second identical request is faster (or hits the cache), not that `CacheInterface::get()` was invoked.

### Modules to test

| Module | Test type | Prior art |
|---|---|---|
| `AgentChatService` with Logger | Feature — verify log file exists after failed API call | `AgentControllerTest` (tests chat endpoint) |
| `VideoGenerationService` with Logger | Feature — verify log file after download failure | `AgentControllerTest` (tests video submission) |
| SSE video stream endpoint | Feature — verify EventSource headers and event format | New test pattern; test response headers + body chunks |
| SSE chat stream endpoint | Feature — verify token events are emitted sequentially | New test pattern |
| Cache hit/miss behavior | Feature — verify second resolve call is cached | `UserSettingsServiceTest` (tests settings lookups) |
| Queue job dispatch | Feature — verify job is inserted into `queue_jobs` table | New test pattern |
| Queue worker execution | Feature — verify job payload is processed and record updated | New test pattern |
| Filesystem write/read | Feature — verify file exists after write, content matches | `VideoGenerationService` implicit tests via media assets |

### Testing approach

- **Logging**: Write a temporary log file path in `setUp()`, assert file contents after triggering an error path. Clean up in `tearDown()`.
- **SSE**: Since Pest doesn't have a native SSE client, test by capturing the `StreamingResponse` output and asserting it contains valid SSE event formatting (`event:`, `data:`, double newline separators).
- **Cache**: Use `CacheInterface` directly in tests — set a value, retrieve it, assert TTL behavior via sleep (or mock the clock if the cache package supports it).
- **Queue**: Dispatch a job in a test, then manually run the worker (or use a sync test mode), assert the side effect (e.g. document status changed to `embedded`).
- **Filesystem**: Write a test file, assert `FilesystemInterface::exists()` and `read()` return correct values.

## Out of Scope

- **WebSocket support** — SSE covers the real-time needs; WebSockets would require additional infrastructure and are overkill for this demo.
- **Redis cache driver** — `cache-file` is sufficient for a single-node Docker setup. Redis would be a future optimization.
- **RabbitMQ queue driver** — `queue-database` is the correct choice for zero-additional-infra. RabbitMQ would be revisited only if queue volume justifies it.
- **S3 filesystem driver** — `filesystem-local` is sufficient. S3 is a future migration path that the abstraction makes possible without code changes.
- **Mail/notification packages** — Not needed for this milestone. Could be a follow-up PRD.
- **Rate limiting** — Out of scope for this PRD; the existing daily run counter is sufficient.
- **Admin panel** — Out of scope.
- **Translation/i18n** — Out of scope.
- **OpenRouter webhook receiver** — The queue-based polling is sufficient; webhooks would require public URL exposure.

## Further Notes

### Debugbar Integration

The existing `marko/debugbar` package already has a `logs` collector that is currently empty. Once `marko/log-file` is installed, debugbar will automatically surface log entries in the web UI. This is a nice showcase detail — developers can see request-scoped logs inline with query times and memory usage.

### Package Showcase Narrative

This PRD intentionally creates a "stack story" that can be demoed end-to-end:

1. **Upload a PDF** → queue job dispatches, UI returns immediately, debugbar shows log entry
2. **Chat with AI** → tokens stream via SSE word-by-word, debugbar shows cache hit on model config
3. **Generate a video** → job submitted, SSE pushes status updates, queue worker downloads the file when ready
4. **Check logs** → `storage/logs/launchpilot-2026-05-04.log` shows the full lifecycle

This tells a more compelling story than any single package in isolation.

### Composer Additions

```json
"require": {
    "marko/log-file": "^0.5",
    "marko/sse": "^0.5",
    "marko/cache-file": "^0.5",
    "marko/queue-database": "^0.5",
    "marko/filesystem-local": "^0.5"
}
```

All packages are `^0.5` to match the existing Marko framework version constraint.
