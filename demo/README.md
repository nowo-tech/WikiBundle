# Wiki Bundle — Demo

Symfony 8.1 demo with FrankenPHP + MySQL.

## Quick start

```bash
cp .env.example .env   # if missing
make up
```

Default URL: `http://localhost:8025` (see `PORT` in `.env.example`).

Opens **directly** on the wiki (`/tools/wiki`). A demo user is signed in automatically (`demo@wiki.local`).

Sample content: **Engineering** and **Product** spaces with nested pages, images, embeds, and internal links. Reload with `make seed` if you had an older minimal fixture.

## What to try

1. Browse spaces and the nested page tree.
2. Edit a page with the Notion-style Tiptap editor (markdown shortcuts, no toolbar).
3. Open version history and diff between revisions.
4. Search within a space.

## Commands

| Target | Description |
|--------|-------------|
| `make up` | Start stack, migrate, load fixtures |
| `make down` | Stop containers |
| `make seed` | Reload demo wiki content |
| `make update-bundle` | Refresh path-repo bundles + `assets:install` |
| `make shell` | Shell in PHP container |

Bundles mounted in the container: `/var/wiki-bundle`, `/var/tiptap-editor-bundle` (path repositories).
