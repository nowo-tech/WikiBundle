# Demo with FrankenPHP

**REQ-DEMO-001:** FrankenPHP demos must install **Nowo Twig Inspector** and **Nowo Hot Reload** together (`nowo-tech/twig-inspector-bundle` + `nowo-tech/hot-reload-bundle` in `require-dev`). Caddyfile: Mercure + `hot_reload` (and `worker { file …; watch }` in worker mode). Do not enable Hot Reload in production.

The Symfony 8 demo runs on [FrankenPHP](https://frankenphp.dev/) inside Docker (Caddy + PHP worker).

## Table of contents

- [Quick start](#quick-start)
- [Stack](#stack)
- [Path repositories](#path-repositories)
- [Commands](#commands)
- [Demo smoke (REQ-TEST-011)](#demo-smoke-req-test-011)
- [Styling](#styling)
- [Production note](#production-note)
- [Switching classic vs worker (`FRANKENPHP_MODE`)](#switching-classic-vs-worker-frankenphp_mode)
- [Timeouts (REQ-RUNTIME-001)](#timeouts-req-runtime-001)
- [Troubleshooting](#troubleshooting)

## Quick start

```bash
make -C demo/symfony8 up
```

Open **http://localhost:8025/tools/wiki** (port from `demo/symfony8/.env`).

## Stack

| Component | Detail |
|-----------|--------|
| Runtime | FrankenPHP 1, PHP 8.4 |
| Web server | Caddy (`Caddyfile`, `Caddyfile.dev`) |
| Database | MySQL 8 (internal Docker network, no exposed DB port) |
| Auth | `DemoAutoLoginAuthenticator` — fixture user `demo@wiki.local` |

## Path repositories

Inside the PHP container:

- `/var/wiki-bundle` — this bundle only (symlink). `nowo-tech/tiptap-editor-bundle` and other deps come from Packagist.

## Commands

```bash
make -C demo/symfony8 down
make -C demo/symfony8 update-bundle   # refresh autoload + cache
make -C demo/symfony8 test            # lint YAML/Twig + schema validate
```

## Demo smoke (REQ-TEST-011)

The smoke test boots the FrankenPHP demo and asserts HTTP 200 on the wiki home:

- **URL:** `http://127.0.0.1:8025/tools/wiki` (the port is `PORT` in `demo/symfony8/.env`, 8025 by default).
- **Command:** `make demo-smoke` from the repository root (it runs `make -C demo demo-smoke`: `up`, then polls the URL for up to 60 tries).
- **CI:** the `Demo smoke` workflow (`.github/workflows/demo-smoke.yml`) runs it weekly (Monday 07:00 UTC), on every `v*` tag and on demand (`workflow_dispatch`).
- **Worker mode:** the demo runs in FrankenPHP worker mode by default (`FRANKENPHP_MODE=worker`). `DEMO_KERNEL_RESET=0 make demo-smoke` keeps the booted kernel alive without running the services resetter between requests (demo `src/Kernel.php`), the way a host with kernel reset off runs it. The smoke pages answer 200 with a clean console in both settings; see the note on the full e2e suite in the CHANGELOG (Unreleased).

## Styling

The demo is a UiKit host (REQ-UI-003, REQ-DEMO-014): `templates/base.html.twig` extends `@NowoUiKit/layout/admin.html.twig` (UiKit masthead and navigation classes, flashes, the theme toggle) with UiKit's default theme and its own `nowo-ui.css` only. It loads no Bootstrap, no CDN and overrides no global token, and it is set as `nowo_ui_kit.layouts.admin` (`css_framework: custom`), so every wiki page renders in it. `nowo-tech/ui-kit-bundle` is a direct requirement of the demo. The bundle loads only `wiki.css` / `wiki.js` (asset package `nowo_wiki`).

## Production note

FrankenPHP worker mode is **not** required for consuming the bundle in your app; the demo uses it for fast local iteration per `REQ-DEMO-002`.

## Switching classic vs worker (`FRANKENPHP_MODE`)

Demos select the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`, hot-reload friendly) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.

## Timeouts (REQ-RUNTIME-001)

Both `Caddyfile` (worker) and `Caddyfile.dev` (classic) set the same hierarchy, innermost first:

| Layer | Value | Where |
| ----- | ----- | ----- |
| LLM call (`nowo_wiki.ai.http_client`) | 30 s (`nowo_wiki.ai.http_timeout`) | `config/packages/ai.yaml` sets `http_client: 'nowo_wiki.ai.http_client'` |
| PHP `max_execution_time` / `max_input_time` | 60 s | `frankenphp { php_ini … }` |
| Caddy `servers.timeouts.write` | 75 s | global `servers { timeouts { write 75s } }` |
| FrankenPHP `max_wait_time` | 30 s | `frankenphp { max_wait_time 30s }` — requests waiting longer for a free thread get a 504 |

Raise all layers together. Smoke check: set `nowo_wiki.ai.http_timeout: 1` and point `OLLAMA_HOST_URL` at a host that accepts the connection but never answers; `/tools/wiki/ask` must return the page with the "could not answer" error after about one second, with a `Wiki AI agent call failed.` error on the `nowo_wiki` log channel.

## Troubleshooting

| Symptom | Cause | Fix |
| --- | --- | --- |
| Twig or PHP changes do not show on refresh | Worker mode keeps the kernel in memory | Set `FRANKENPHP_MODE=classic` in `demo/symfony8/.env`, then `make -C demo/symfony8 up` (recreates the container), or `make -C demo/symfony8 restart` after edits |
| Changing `FRANKENPHP_MODE` has no effect | `docker compose restart` does not reload `.env` | Run `make -C demo/symfony8 up` / `docker compose up -d` so the container is recreated |
| `Unknown FRANKENPHP_MODE=…` and the container exits | Value other than `classic` / `worker` | Fix the value in `.env` |
| Bundle changes not picked up | Path repository autoload or cache is stale | `make -C demo/symfony8 update-bundle` |
| Port already in use | Another stack uses `PORT` | Change `PORT` and `DEFAULT_URI` in `demo/symfony8/.env` |
| Database errors after pulling changes | Schema or fixtures are out of date | `make -C demo/symfony8 database` then `make -C demo/symfony8 seed` |
| Wiki AI answers fail | No AI provider configured | Set `OPENAI_API_KEY` or point `OLLAMA_HOST_URL` at a running Ollama server |
