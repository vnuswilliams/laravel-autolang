<?php

namespace VnusWilliams\LaravelAutoLang\Tests\Unit;

use VnusWilliams\LaravelAutoLang\Services\BladeTransformer;
use VnusWilliams\LaravelAutoLang\Tests\TestCase;

class BladeTransformerTest extends TestCase
{
    public function test_it_wraps_detected_text_with_translation_helper(): void
    {
        $content = "<h1>Bienvenue</h1>\n<p> C'est prêt </p>";

        $transformer = new BladeTransformer();
        $result = $transformer->transform($content, ['Bienvenue', "C'est prêt"]);

        $this->assertStringContainsString("<h1>{{ __('Bienvenue') }}</h1>", $result);
        $this->assertStringContainsString("<p> {{ __('C\\'est prêt') }} </p>", $result);
    }
}
