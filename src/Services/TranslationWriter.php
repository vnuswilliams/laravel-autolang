<?php

namespace Vnuswilliams\LaravelAutoLang\Services;

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

        return $output === 'php'
            ? $this->appendPhp($locale, $strings, (string) $phpFile)
            : $this->appendJson($locale, $strings);
    }

    /** @param array<int,string> $strings */
    private function appendJson(string $locale, array $strings): int
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
                $existing[$string] = $string;
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
    private function appendPhp(string $locale, array $strings, string $fileName): int
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
        $prefix = $fileName.'.';

        foreach ($strings as $string) {
            $baseKey = $this->basePhpKey($string);
            $candidate = $prefix.$baseKey;

            if (array_key_exists($candidate, $existing)) {
                $candidate = $prefix.$this->fallbackPhpKey($string);
            }

            if (! array_key_exists($candidate, $existing)) {
                $existing[$candidate] = $string;
            }
        }

        ksort($existing);

        $export = var_export($existing, true);
        $content = "<?php\n\nreturn {$export};\n";
        $this->files->put($path, $content);

        return count($existing) - $beforeCount;
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

    private function basePhpKey(string $value): string
    {
        $words = preg_split('/\s+/', trim($value)) ?: [];

        return strtolower($words[0] ?? 'translation');
    }

    private function fallbackPhpKey(string $value): string
    {
        return strtolower((string) preg_replace('/\s+/', '', trim($value)));
    }
}
