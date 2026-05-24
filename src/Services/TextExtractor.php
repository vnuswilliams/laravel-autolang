<?php

namespace VnusWilliams\LaravelAutoLang\Services;

class TextExtractor
{
    /**
     * Extract translatable text candidates from Blade content.
     *
     * @return array<int, string>
     */
    public function extractTranslatableStrings(string $content): array
    {
        $content = $this->maskIgnoredBlocks($content);

        preg_match_all('/>([^<]+)</u', $content, $matches);

        $strings = [];

        foreach ($matches[1] as $segment) {
            $candidate = trim(html_entity_decode($segment, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

            if (! $this->isValidCandidate($candidate)) {
                continue;
            }

            $strings[] = preg_replace('/\s+/u', ' ', $candidate) ?? $candidate;
        }

        return array_values(array_unique($strings));
    }

    /**
     * Extract existing translation keys already wrapped with __().
     *
     * @return array<int, string>
     */
    public function extractTranslationKeys(string $content): array
    {
        preg_match_all('/__\(\s*([\'"])(.*?)\1\s*\)/u', $content, $matches);

        $keys = [];
        foreach ($matches[2] ?? [] as $key) {
            $candidate = $this->normalizeTranslationKey((string) $key);
            if ($candidate === '') {
                continue;
            }

            $keys[] = preg_replace('/\s+/u', ' ', $candidate) ?? $candidate;
        }

        return array_values(array_unique($keys));
    }

    /**
     * Remove blocks that should be ignored during extraction.
     */
    private function maskIgnoredBlocks(string $content): string
    {
        $patterns = [
            '/<\?php[\s\S]*?\?>/i',
            '/<script\b[^>]*>.*?<\/script>/is',
            '/<style\b[^>]*>.*?<\/style>/is',
            '/@php[\s\S]*?@endphp/',
            '/{{\s*__\(.*?\)\s*}}/s',
            '/{{.*?}}/s',
            '/{!!.*?!!}/s',
            '/@\w+(\s*\(.*?\))?/s',
            '/<x-[^>]*\/>/s',
            '/<x-[^>]*>.*?<\/x-[^>]*>/s',
        ];

        foreach ($patterns as $pattern) {
            $content = preg_replace($pattern, '', $content) ?? $content;
        }

        return $content;
    }

    /**
     * Determine whether a candidate should be translated.
     */
    private function isValidCandidate(string $text): bool
    {
        if ($text === '') {
            return false;
        }

        if (! preg_match('/[\p{L}\p{N}]/u', $text)) {
            return false;
        }

        if (str_contains($text, '<?php') || str_contains($text, '@')) {
            return false;
        }

        return true;
    }

    private function normalizeTranslationKey(string $key): string
    {
        $candidate = trim($key);
        if ($candidate === '') {
            return '';
        }

        if (! str_contains($candidate, '.')) {
            return $candidate;
        }

        $parts = array_values(array_filter(explode('.', $candidate), static fn (string $part): bool => $part !== ''));

        return $parts === [] ? $candidate : (string) end($parts);
    }
}
