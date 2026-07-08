# Upgrading

## To 1.0.0

Initial stable release. No prior versions.

### Requirements

- PHP `>=8.2 <8.6`
- Symfony `^7.4 || ^8.0`
- `nowo-tech/tiptap-editor-bundle` `^1.0`
- Doctrine ORM 2.15+ or 3.x

### Install / upgrade steps

```bash
composer require nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

1. Add `config/packages/nowo_wiki.yaml` with `user_class`.
2. Run Doctrine migrations for `wiki_spaces`, `wiki_pages`, `wiki_page_revisions` (or your `table_prefix`).
3. Implement `WikiAccessCheckerInterface` (or use default role-based checker).
4. For team spaces, implement `WikiTeamMembershipResolverInterface`.
5. Clear cache: `bin/console cache:clear`.

### Optional features

**Import / export** (enabled by default):

```yaml
nowo_wiki:
    import_export:
        enabled: true
        max_upload_bytes: 52428800  # 50 MB
```

Console: `bin/console wiki:import {space} {source}` and `bin/console wiki:export {space} {target} [--zip]`.

**Symfony AI assistant**:

```bash
composer require symfony/ai-bundle
```

```yaml
nowo_wiki:
    ai:
        enabled: true
        agent: wiki_assistant
```

See [CONFIGURATION.md](CONFIGURATION.md) and [USAGE.md](USAGE.md).

### Breaking changes

None (first release).

See [CHANGELOG.md](CHANGELOG.md) for full history.
