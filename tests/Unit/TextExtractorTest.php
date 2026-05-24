<?php

namespace VnusWilliams\LaravelAutoLang\Tests\Unit;

use VnusWilliams\LaravelAutoLang\Services\TextExtractor;
use VnusWilliams\LaravelAutoLang\Tests\TestCase;

class TextExtractorTest extends TestCase
{
    public function test_it_extracts_valid_strings_and_ignores_masked_blocks(): void
    {
        $content = <<<'BLADE'
<div>Bienvenue</div>
<p>   Bonjour tout le monde   </p>
<script>alert('ignore me');</script>
@php $a = 'ignore'; @endphp
<span>{{ $dynamic }}</span>
<x-button>Ignore component</x-button>
BLADE;

        $extractor = new TextExtractor();

        $strings = $extractor->extractTranslatableStrings($content);

        $this->assertSame(['Bienvenue', 'Bonjour tout le monde'], $strings);
    }

    public function test_it_extracts_prefixed_translation_keys_as_canonical_leaf_key(): void
    {
        $content = <<<'BLADE'
<h1>{{ __('prefix.lacle') }}</h1>
<p>{{ __('prefix.unautreprefix.lacle2') }}</p>
BLADE;

        $extractor = new TextExtractor();

        $keys = $extractor->extractTranslationKeys($content);

        $this->assertSame(['lacle', 'lacle2'], $keys);
    }
}
