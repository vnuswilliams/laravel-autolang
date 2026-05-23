<?php

namespace Vnuswilliams\LaravelAutoLang\Services;

use Illuminate\Filesystem\Filesystem;

class JsonTranslationWriter
{
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * @param  array<int, string>  $strings
     */
    public function append(string $locale, array $strings): int
    {
        if ($strings === []) {
            return 0;
        }

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
}
