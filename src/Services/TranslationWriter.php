<?php

namespace VnusWilliams\LaravelAutoLang\Services;

use Illuminate\Filesystem\Filesystem;

class TranslationWriter
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @param  array<int, string>  $strings
     */
    public function append(string $locale, array $strings, string $output, ?string $phpFile = null): int
    {
        if ($strings === []) {
            return 0;
        }

        $fallbackValues = $this->loadFallbackValues();

        return $output === 'php'
            ? $this->appendPhp($locale, $strings, (string) $phpFile, $fallbackValues)
            : $this->appendJson($locale, $strings, $fallbackValues);
    }

    /** @param array<int,string> $strings */
    private function appendJson(string $locale, array $strings, array $fallbackValues): int
    {
        $langPath = lang_path("{$locale}.json");

        if (! $this->files->exists(dirname($langPath))) {
            $this->files->makeDirectory(dirname($langPath), 0755, true);
        }

        $existing = [];
        if ($this->files->exists($langPath)) {
            $decoded = json_decode($this->files->get($langPath), true);
            if (is_array($decoded)) {
                $existing = $decoded;
            }
        }

        $beforeCount = count($existing);

        foreach ($strings as $string) {
            if (! array_key_exists($string, $existing)) {
                $existing[$string] = $fallbackValues[$string] ?? $string;
            }
        }

        ksort($existing);

        $this->files->put(
            $langPath,
            json_encode($existing, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
        );

        return count($existing) - $beforeCount;
    }

    /** @param array<int,string> $strings */
    private function appendPhp(string $locale, array $strings, string $fileName, array $fallbackValues): int
    {
        $langDir = lang_path($locale);

        if (! $this->files->exists($langDir)) {
            $this->files->makeDirectory($langDir, 0755, true);
        }

        $fileName = $this->normalizePhpFileName($fileName);
        $path = $langDir.'/'.$fileName.'.php';

        $existing = [];
        if ($this->files->exists($path)) {
            $loaded = include $path;
            if (is_array($loaded)) {
                $existing = $loaded;
            }
        }

        $beforeCount = count($existing);

        foreach ($strings as $string) {
            $candidate = $this->translationKey($string);

            if (! array_key_exists($candidate, $existing)) {
                $existing[$candidate] = $fallbackValues[$string] ?? $string;
            }
        }

        ksort($existing);
        $content = $this->buildPhpArrayFile($existing);
        $this->files->put($path, $content);

        return count($existing) - $beforeCount;
    }

    /** @return array<string, string> */
    private function loadFallbackValues(): array
    {
        $fallbackPath = lang_path('en.json');
        if (! $this->files->exists($fallbackPath)) {
            return [];
        }

        $decoded = json_decode($this->files->get($fallbackPath), true);

        return is_array($decoded) ? $decoded : [];
    }



    private function normalizePhpFileName(string $value): string
    {
        $value = (string) preg_replace('/\.php$/i', '', trim($value));
        $clean = (string) preg_replace('/[^a-zA-Z0-9\s]+/', ' ', $value);
        $parts = preg_split('/\s+/', trim($clean)) ?: [];

        if ($parts === []) {
            return 'messages';
        }

        $camel = strtolower((string) array_shift($parts));
        foreach ($parts as $part) {
            $camel .= ucfirst(strtolower($part));
        }

        return $camel !== '' ? $camel : 'messages';
    }

    private function translationKey(string $value): string
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '';

        return $normalized !== '' ? $normalized : 'translation';
    }

    /** @param array<string, string> $values */
    private function buildPhpArrayFile(array $values): string
    {
        $lines = ["<?php", '', 'return ['];
        foreach ($values as $key => $value) {
            $escapedKey = addcslashes($key, "\\\"");
            $escapedValue = addcslashes($value, "\\\"");
            $lines[] = '    "'.$escapedKey.'" => "'.$escapedValue.'",';
        }
        $lines[] = '];';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}
