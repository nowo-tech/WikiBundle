# Installation — WikiBundle

## Requirements

- PHP 8.2+
- Symfony 7.4, 8.0, or 8.1
- Doctrine ORM
- [nowo-tech/tiptap-editor-bundle](https://github.com/nowo-tech/TiptapEditorBundle)

## Composer

```bash
composer require nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

Register bundles if Flex does not:

```php
// config/bundles.php
Nowo\TiptapEditorBundle\NowoTiptapEditorBundle::class => ['all' => true],
Nowo\WikiBundle\WikiBundle::class => ['all' => true],
```

## Routing

```yaml
# config/routes/nowo_wiki.yaml
nowo_wiki:
    resource: .
    type: nowo_wiki
```

## Database

Run Doctrine migrations in your application after entities are mapped (bundle prepends ORM mapping).

## Tiptap editor

Configure a `notion` profile (or override `nowo_wiki.editor.tiptap_config`):

```yaml
# config/packages/nowo_tiptap_editor.yaml
nowo_tiptap_editor:
    configs:
        notion:
            variant: notion
            toolbar: true
            min_height: 520px
            form_theme: form_div_layout.html.twig
```

Publish bundle assets (wiki + Tiptap) and load the editor script on edit pages (included automatically when using `@NowoWikiBundle/layout.html.twig` and `page_edit.html.twig`):

```bash
php bin/console assets:install public
```

If you override the wiki layout, keep `{% block wiki_editor_scripts %}` or add:

```twig
<script src="{{ asset(nowo_tiptap_editor_asset_path('tiptap-editor.js')) }}"></script>
```
