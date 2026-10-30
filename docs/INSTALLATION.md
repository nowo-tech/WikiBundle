# Installation — WikiBundle

## Table of contents

- [Requirements](#requirements)
- [Composer](#composer)
- [Routing](#routing)
- [Database](#database)
- [Tiptap editor](#tiptap-editor)
- [Twig Extra Bundle (REQ-TWIG-004)](#twig-extra-bundle-req-twig-004)

## Requirements

- **FormKitBundle** (`nowo-tech/form-kit-bundle` ^2.0) — dashboard/admin Symfony forms (`FormOptionsTrait`, profile `wiki`). Register `NowoFormKitBundle` in `config/bundles.php` (Flex / demo). Optional host YAML: `config/packages/nowo_form_kit.yaml`.

- PHP 8.2+
- Symfony 7.4, 8.0, or 8.1
- Doctrine ORM `^3.7` (QueryBuilder sort directions use `\SortDirection`, polyfilled by `symfony/polyfill-php86`)
- [nowo-tech/tiptap-editor-bundle](https://github.com/nowo-tech/TiptapEditorBundle)

## Composer

```bash
composer require nowo-tech/wiki-bundle nowo-tech/tiptap-editor-bundle
```

The Flex recipe lives under `.symfony/recipe/` (copy those files if Flex does not apply them).

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

Publish bundle assets (wiki + Tiptap + UiKit); the page editor (`page_edit.html.twig`) adds the Tiptap script to the UiKit `nowo_ui_scripts` block itself:

```bash
php bin/console assets:install public
```

The wiki pages render inside `nowo_ui_kit.layouts.admin` (your layout or a one-file bridge rendering the UiKit canonical blocks, including `nowo_ui_scripts`); see [CONFIGURATION](CONFIGURATION.md#web-ui-req-ui-001--req-ui-003) and [TEMPLATES](TEMPLATES.md). Give the host a form theme (`twig.form_themes`, e.g. `bootstrap_5_layout.html.twig`) or a FormKit profile: the wiki form types carry no framework class.

## Twig Extra Bundle (REQ-TWIG-004)

This package ships Twig templates. Host applications **must** install and enable Twig Extra:

```bash
composer require twig/extra-bundle twig/string-extra
```

Register `Twig\Extra\TwigExtraBundle\TwigExtraBundle` in `config/bundles.php` (Flex usually does this). Demos already include the same stack. The package `release-check` runs `make check-twig-extra` to guard this contract.
