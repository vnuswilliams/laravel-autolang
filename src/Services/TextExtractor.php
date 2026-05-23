<?php

namespace Vnuswilliams\LaravelAutoLang\Services;

class TextExtractor
{
    /**
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

    private function maskIgnoredBlocks(string $content): string
    {
        $patterns = [
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
}
