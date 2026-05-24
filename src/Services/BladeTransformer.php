<?php

namespace VnusWilliams\LaravelAutoLang\Services;

class BladeTransformer
{
    /**
     * Wrap plain text segments in Blade translation helpers.
     *
     * @param  array<int, string>  $strings
     */
    public function transform(string $content, array $strings, ?string $phpFile = null, string $output = 'json'): string
    {
        if ($strings === []) {
            return $content;
        }

        $escaped = array_map(static fn (string $text): string => preg_quote($text, '/'), $strings);
        usort($escaped, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $alternation = implode('|', $escaped);

        return preg_replace_callback(
            '/>(\s*)('.$alternation.')(\s*)</u',
            function (array $matches) use ($phpFile, $output): string {
                $leading = $matches[1];
                $text = $matches[2];
                $trailing = $matches[3];

                if (str_contains($text, "{{ __('")) {
                    return $matches[0];
                }

                $key = $this->translationKey($text);
                if ($output === 'php') {
                    $prefix = $this->normalizePhpFileName((string) $phpFile);
                    $key = "{$prefix}.{$key}";
                }

                return '>'.$leading.'{{ __("'.$key.'") }}'.$trailing.'<';
            },
            $content
        ) ?? $content;
    }

    private function translationKey(string $value): string
    {
        $normalized = mb_strtolower($value, 'UTF-8');
        $normalized = preg_replace('/[^\p{L}\p{N}]+/u', '', $normalized) ?? '';

        return $normalized !== '' ? $normalized : 'translation';
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
}
