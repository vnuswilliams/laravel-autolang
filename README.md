# Laravel AutoLang

Automatically convert hardcoded Blade text into Laravel translations.

## Install

```bash
composer require vnuswilliams/laravel-autolang
```

## Publish config

```bash
php artisan vendor:publish --provider="Vnuswilliams\\LaravelAutoLang\\LaravelAutoLangServiceProvider" --tag=config
```

## Run

```bash
php artisan lang:auto
php artisan lang:auto --dry
php artisan lang:auto --force
```
