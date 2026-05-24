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
}
