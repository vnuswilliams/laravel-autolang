<?php

namespace Vnuswilliams\LaravelAutoLang\Services;

class BladeTransformer
{
    /**
     * @param  array<int, string>  $strings
     */
    public function transform(string $content, array $strings): string
    {
        if ($strings === []) {
            return $content;
        }

        $escaped = array_map(static fn (string $text): string => preg_quote($text, '/'), $strings);
        usort($escaped, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));
        $alternation = implode('|', $escaped);

        return preg_replace_callback(
            '/>(\s*)('.$alternation.')(\s*)</u',
            static function (array $matches): string {
                $leading = $matches[1];
                $text = $matches[2];
                $trailing = $matches[3];

                if (str_contains($text, "{{ __('")) {
                    return $matches[0];
                }

                $text = str_replace("'", "\\'", $text);

                return '>'.$leading."{{ __('{$text}') }}".$trailing.'<';
            },
            $content
        ) ?? $content;
    }
}
