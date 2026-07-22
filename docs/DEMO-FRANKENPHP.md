# Demo with FrankenPHP

The Symfony 8 demo runs on [FrankenPHP](https://frankenphp.dev/) inside Docker (Caddy + PHP worker).

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

- `/var/wiki-bundle` — this bundle (symlink)
- `/var/tiptap-editor-bundle` — Tiptap editor bundle

## Commands

```bash
make -C demo/symfony8 down
make -C demo/symfony8 update-bundle   # refresh autoload + cache
make -C demo/symfony8 test            # lint YAML/Twig + schema validate
```

## Styling

The demo overrides `@NowoWikiBundle/layout.html.twig` with Tabler CSS and `public/css/demo.css`. Wiki UI styles live in the bundle asset `wiki.css`.

## Production note

FrankenPHP worker mode is **not** required for consuming the bundle in your app; the demo uses it for fast local iteration per `REQ-DEMO-002`.

## Switching classic vs worker (`FRANKENPHP_MODE`)

Demos select the FrankenPHP runtime via **`FRANKENPHP_MODE`** in `.env` / `.env.example` (not a Dockerfile `ENV`):

| Value | Behaviour |
| --- | --- |
| **`worker`** (default) | Keep the worker Caddyfile (`php_server { worker ... }`) |
| **`classic`** | Entrypoint copies `Caddyfile.dev` (plain `php_server`, hot-reload friendly) |

Compose passes `FRANKENPHP_MODE=${FRANKENPHP_MODE:-worker}` into the PHP service. After changing `.env`, run `docker compose up -d` (or `make up`) so the container is **recreated** — a plain `restart` does not reload env. No image rebuild is required.
