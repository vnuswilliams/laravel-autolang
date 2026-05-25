<?php

namespace VnusWilliams\LaravelAutoLang\Services;

use Illuminate\Filesystem\Filesystem;

class BladeReverter
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    // ====================================================================
    // Core revert logic
    // ====================================================================

    /**
     * Replace every {{ __("...") }} / {{ __('...') }} helper in $content
     * with the raw translation value found in $translations.
     *
     * Key resolution rule: last segment after the last dot.
     *   "welcome"          → "welcome"
     *   "messages.welcome" → "welcome"
     *   "a.b.c.myKey"      → "myKey"
     *
     * If a key is not found in $translations the helper is left untouched.
     *
     * @param  array<string, string>  $translations
     * @return array{content: string, replaced: array<string, string>}
     */
    public function revert(string $content, array $translations): array
    {
        $replaced = [];

        $result = preg_replace_callback(
            '/\{\{\s*__\(\s*([\'"])(.*?)\1\s*\)\s*\}\}/u',
            function (array $matches) use ($translations, &$replaced): string {
                $leafKey = $this->resolveLeafKey($matches[2]);

                if (! array_key_exists($leafKey, $translations)) {
                    return $matches[0];
                }

                $replaced[$leafKey] = $translations[$leafKey];

                return $translations[$leafKey];
            },
            $content
        );

        return [
            'content'  => $result ?? $content,
            'replaced' => $replaced,
        ];
    }

    /**
     * Extract the last segment after the final dot.
     *
     * "messages.welcome"  → "welcome"
     * "a.b.c.myKey"       → "myKey"
     * "welcome"           → "welcome"
     */
    public function resolveLeafKey(string $key): string
    {
        preg_match('/([^.]+)$/u', trim($key), $m);

        return $m[1] ?? trim($key);
    }

    // ====================================================================
    // Translation file — loaders
    // ====================================================================

    /**
     * Return the list of PHP translation file names (without extension)
     * found in the given directory.
     *
     * @return array<int, string>
     */
    public function listPhpFiles(string $langDir): array
    {
        if (! $this->files->isDirectory($langDir)) {
            return [];
        }

        return array_values(array_map(
            static fn ($f) => pathinfo($f->getFilename(), PATHINFO_FILENAME),
            array_filter(
                $this->files->files($langDir),
                static fn ($f) => str_ends_with($f->getFilename(), '.php')
            )
        ));
    }

    /**
     * Load and return the translations array from a PHP translation file.
     *
     * @return array<string, string>
     */
    public function loadPhpTranslations(string $filePath): array
    {
        if (! $this->files->exists($filePath)) {
            return [];
        }

        $loaded = include $filePath;

        return is_array($loaded) ? $loaded : [];
    }

    /**
     * Load and return the translations array from a JSON translation file.
     *
     * @return array<string, string>
     */
    public function loadJsonTranslations(string $locale): array
    {
        $path = lang_path("{$locale}.json");

        if (! $this->files->exists($path)) {
            return [];
        }

        $decoded = json_decode($this->files->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    // ====================================================================
    // Translation file — writers
    // ====================================================================

    /**
     * Remove $usedKeys from the translation file and rewrite it.
     *
     * @param  array<int, string>  $usedKeys
     */
    public function removeKeys(string $translationPath, array $usedKeys, string $output): void
    {
        if ($output === 'php') {
            $existing = include $translationPath;
            if (! is_array($existing)) {
                return;
            }
            foreach ($usedKeys as $key) {
                unset($existing[$key]);
            }
            ksort($existing);
            $this->files->put($translationPath, $this->buildPhpArrayFile($existing));
        } else {
            $decoded = json_decode($this->files->get($translationPath), true);
            if (! is_array($decoded)) {
                return;
            }
            foreach ($usedKeys as $key) {
                unset($decoded[$key]);
            }
            ksort($decoded);
            $this->files->put(
                $translationPath,
                json_encode($decoded, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES).PHP_EOL
            );
        }
    }

    /**
     * Build the PHP return array file content from a key/value map.
     *
     * @param  array<string, string>  $values
     */
    public function buildPhpArrayFile(array $values): string
    {
        $lines = ['<?php', '', 'return ['];
        foreach ($values as $key => $value) {
            $escapedKey   = addcslashes($key, '\\"');
            $escapedValue = addcslashes($value, '\\"');
            $lines[]      = '    "'.$escapedKey.'" => "'.$escapedValue.'",';
        }
        $lines[] = '];';
        $lines[] = '';

        return implode(PHP_EOL, $lines);
    }
}