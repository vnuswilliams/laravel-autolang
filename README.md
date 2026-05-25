````md
<p align="center">
    <a href="https://packagist.org/packages/vnuswilliams/laravel-autolang">
        <img src="https://img.shields.io/packagist/v/vnuswilliams/laravel-autolang" alt="Latest Version">
    </a>

    <a href="https://packagist.org/packages/vnuswilliams/laravel-autolang">
        <img src="https://img.shields.io/packagist/dt/vnuswilliams/laravel-autolang" alt="Total Downloads">
    </a>

    <a href="https://packagist.org/packages/vnuswilliams/laravel-autolang">
        <img src="https://img.shields.io/packagist/php-v/vnuswilliams/laravel-autolang" alt="PHP Version">
    </a>

    <a href="https://packagist.org/packages/vnuswilliams/laravel-autolang">
        <img src="https://img.shields.io/badge/Laravel-10%20--%2013-red" alt="Laravel Version">
    </a>

    <a href="LICENSE.md">
        <img src="https://img.shields.io/badge/license-MIT-green" alt="License">
    </a>

    <a href="https://github.com/vnuswilliams/laravel-autolang/actions">
        <img src="https://img.shields.io/github/actions/workflow/status/vnuswilliams/laravel-autolang/tests.yml" alt="Tests">
    </a>

    <a href="https://github.com/vnuswilliams/laravel-autolang">
        <img src="https://img.shields.io/badge/code%20style-Pint-blue" alt="Code Style">
    </a>
</p>

# Laravel AutoLang

Laravel AutoLang automates the internationalization of your Blade views by:

- detecting hardcoded text inside `*.blade.php` templates,
- replacing this text with `{{ __('...') }}`,
- automatically adding missing entries into `lang/<locale>.json` or `lang/<locale>/<file>.php`.

The goal is to reduce manual work when setting up or maintaining multilingual support in a Laravel project.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Available Command](#available-command)
- [Usage Examples](#usage-examples)
- [Supported Scenarios](#supported-scenarios)
- [Ignored Cases / Known Limitations](#ignored-cases--known-limitations)
- [Recommended Team Workflow](#recommended-team-workflow)
- [Troubleshooting](#troubleshooting)
- [Laravel i18n Best Practices](#laravel-i18n-best-practices)

---

## Features

- Scan one or multiple Blade view directories.
- Detect text segments between HTML tags.
- Automatically replace text with `{{ __('Text') }}`.
- Generate/update translations in JSON or PHP format.
- Automatically name PHP translation files based on scanned Blade filenames.
- Deduplicate existing translation keys.
- Preview mode (`--dry`) before writing changes.
- Interactive confirmation (disable with `--force`).

---

## Requirements

- PHP version compatible with your Laravel version.
- A Laravel project using the standard structure (`resources/views`, `lang`, etc.).
- Write permissions for view files and language directories.

---

## Installation

```bash
composer require --dev vnuswilliams/laravel-autolang
````

---

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --provider="VnusWilliams\\LaravelAutoLang\\LaravelAutoLangServiceProvider" --tag=config
```

The `config/lang-auto.php` file exposes:

```php
return [
    'paths' => [
        resource_path('views'),
    ],

    'extensions' => [
        '.blade.php',
    ],

    'locale' => 'en',
    'output' => 'json',
];
```

### `paths`

List of directories to scan for `*.blade.php` files.

Example with multiple locations:

```php
'paths' => [
    resource_path('views'),
    base_path('Modules/Blog/resources/views'),
],
```

### `extensions`

List of allowed file extensions during scanning.

Default:

```php
'extensions' => ['.blade.php'],
```

### `locale`

Target locale for translation files.

```php
'locale' => 'fr',
```

### `output`

Output format: `json` (default) or `php`.

When `output = php`, the PHP translation filename is automatically derived from the scanned Blade filename — no extra configuration is required.

---

## Automatic PHP Translation File Naming

When `output = php`, the translation file name is generated from the Blade filename according to these rules:

| Blade File                | Translation File                 |
| ------------------------- | -------------------------------- |
| `welcome.blade.php`       | `lang/<locale>/welcome.php`      |
| `leave-balance.blade.php` | `lang/<locale>/leavebalance.php` |
| `⚡welcome-to.blade.php`   | `lang/<locale>/welcometo.php`    |
| `My_Cool View.blade.php`  | `lang/<locale>/mycoolview.php`   |

**Applied rules:**

1. Extensions are removed (`.blade.php` → two passes).
2. The result is converted to lowercase.
3. Any non-Unicode letter or digit character is removed.

With `--all`, each Blade file produces its own PHP translation file.

---

## Available Command

```bash
php artisan lang:auto {path?}
```

* `path` (optional): relative path from `resources/views` (or configured root path), without extension.
* If `path` is missing, the command asks interactively.
* If the provided path starts with `/`, the leading slash is automatically removed.

Example paths:

* `welcome` ⟶ searches `resources/views/welcome.blade.php`
* `pages/welcome` ⟶ searches `resources/views/pages/welcome.blade.php`

Options:

* `--all` : scan the entire configured directory recursively.
* `--locale=fr` : temporarily override the output locale.
* `--output=json|php` : choose output format (overrides config).
* `--dry` : simulate execution without modifying files.
* `--force` : apply changes without confirmation.

With `--all`, an extra confirmation is requested before starting the global scan (unless `--force` is present).

Full signature:

```bash
php artisan lang:auto {path?} {--all} {--locale=} {--output=} {--dry} {--force}
```

---

## Usage Examples

### 1) First Translation Migration (JSON)

```bash
php artisan lang:auto --locale=fr
```

* The package detects hardcoded strings.
* Displays detected strings and impacted files.
* After confirmation, updates views and `lang/fr.json`.

### 2) PHP Output — Automatic Naming

```bash
php artisan lang:auto leave-balance --locale=fr --output=php --force
```

* Scans `resources/views/leave-balance.blade.php`.
* Automatically creates/updates `lang/fr/leavebalance.php`.

### 3) Global PHP Scan

```bash
php artisan lang:auto --all --locale=fr --output=php --force
```

* Scans all views.
* Each view generates its own PHP translation file:

  * `welcome.blade.php` → `lang/fr/welcome.php`
  * `hr/leave-balance.blade.php` → `lang/fr/leavebalance.php`

### 4) Verification Before Commit (Local CI)

```bash
php artisan lang:auto --dry
```

* No files modified.
* Allows reviewing pending changes before applying them.

### 5) Non-Interactive Execution (Scripts / CI)

```bash
php artisan lang:auto --force
```

---

## Supported Scenarios

### Simple HTML Text

Before:

```blade
<h1>Welcome</h1>
```

After:

```blade
<h1>{{ __('Welcome') }}</h1>
```

### Preserving Surrounding Spaces

Before:

```blade
<p>   Hello world   </p>
```

After:

```blade
<p>   {{ __('Hello world') }}   </p>
```

### Apostrophes

Before:

```blade
<span>It's ready</span>
```

After:

```blade
<span>{{ __('It\'s ready') }}</span>
```

### JSON Translation Deduplication

If a key already exists in `lang/<locale>.json`, it is not duplicated.
The file is alphabetically sorted to keep clean diffs.

---

## Ignored Cases / Known Limitations

To avoid false positives, some blocks are ignored during extraction:

* `<script>...</script>`
* `<style>...</style>`
* `@php ... @endphp` blocks
* Blade expressions `{{ ... }}` and `{!! ... !!}`
* Blade directives (`@if`, `@foreach`, etc.)
* Blade components `<x-... />` and `<x-...>...</x-...>`

### Important Limitations

* The package targets text between tags (`>text<`).

  * HTML attributes are not automatically translated (`placeholder`, `title`, etc.).
* The package does not translate content:

  * it creates key=value entries (example: `"Hello": "Hello"`).
* For highly dynamic or unusual Blade structures, always review changes before committing.

---

## Recommended Team Workflow

1. Create a dedicated branch.

2. Run a dry-run:

   ```bash
   php artisan lang:auto --dry
   ```

3. Execute for real:

   ```bash
   php artisan lang:auto --force
   ```

4. Review diffs for:

   * modified Blade views,
   * `lang/<locale>/*.php` or `lang/<locale>.json` files.

5. Commit clearly (example: `chore(i18n): auto-wrap blade strings`).

---

## Troubleshooting

### "No Blade files found."

* Check paths in `config/lang-auto.php` (`paths`).
* Ensure directories exist and contain `*.blade.php` files.

### "No new translatable strings found."

* Strings may already be translated.
* Content may be inside ignored blocks (`script`, `style`, components, dynamic Blade).

### PHP translation file is not created

* Check write permissions for the `lang/` directory.
* Verify the selected locale (`--locale` or config).

---

## Laravel i18n Best Practices

* Use stable and consistent phrases.
* Avoid string concatenation inside views.
* Prefer Laravel placeholders (`__('Welcome :name', ['name' => $name])`) for dynamic content.
* Add QA/product review for translations.

---

## License

MIT

---

## Reverse Command — `--reverse`

The `--reverse` command allows reverting changes: it reads translation values, replaces `{{ __('...') }}` helpers with raw text in Blade views, then removes used keys from translation files.

```bash
php artisan lang:auto {path?} --reverse
php artisan lang:auto --all --reverse
```

The flag does not take any value. Everything relies on the configuration (`locale`, `output`).

---

### Workflow Depending on `output`

**`json` mode**

1. Load `lang/<locale>.json`
2. In targeted Blade files, resolve each `{{ __("...") }}` → replace with raw value
3. Remove used keys from the `.json` file and rewrite it

**`php` mode**

1. List available `.php` files in `lang/<locale>/` and ask for selection interactively
2. Load translations from the selected file
3. In targeted Blade files, resolve each `{{ __("...") }}` → replace with raw value
4. Remove used keys from the `.php` file and rewrite it

---

### Key Resolution

The last segment after the final `.` is used as the lookup key inside translation files.

| Blade Helper                   | Resolved Key |
| ------------------------------ | ------------ |
| `{{ __("welcome") }}`          | `welcome`    |
| `{{ __("messages.welcome") }}` | `welcome`    |
| `{{ __("a.b.c.myKey") }}`      | `myKey`      |

If a key is not found in the translation file, the helper remains unchanged.

---

### Full Example

Blade before:

```blade
<h1>{{ __("welcome") }}</h1>
<p>{{ __("messages.farewell") }}</p>
```

`lang/fr.json`:

```json
{
    "farewell": "Goodbye",
    "welcome": "Welcome"
}
```

After:

```bash
php artisan lang:auto welcome --reverse --locale=fr
```

Blade result:

```blade
<h1>Welcome</h1>
<p>Goodbye</p>
```

`lang/fr.json` after cleanup:

```json
{}
```

---

### Compatible Options With `--reverse`

| Option               | Behavior                           |
| -------------------- | ---------------------------------- |
| `--dry`              | Preview without modifying files    |
| `--force`            | Apply without confirmation         |
| `--all`              | Process all configured views       |
| `--locale=fr`        | Temporarily override locale        |
| `--output=json\|php` | Temporarily override output format |

---

### Limitations

* Only keys present in the selected translation file are resolved.
* HTML attributes (`placeholder`, `title`, etc.) are not handled — consistent with forward behavior.
* If `lang/<locale>/` contains no `.php` files, the command stops with a warning.

```
```
