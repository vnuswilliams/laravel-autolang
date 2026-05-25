<?php

namespace VnusWilliams\LaravelAutoLang\Tests\Unit;

use Illuminate\Filesystem\Filesystem;
use VnusWilliams\LaravelAutoLang\Services\BladeReverter;
use VnusWilliams\LaravelAutoLang\Tests\TestCase;

class BladeReverterTest extends TestCase
{
    private BladeReverter $reverter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reverter = new BladeReverter(new Filesystem());
    }

    // ------------------------------------------------------------------
    // resolveLeafKey
    // ------------------------------------------------------------------

    public function test_resolve_leaf_key_returns_key_as_is_when_no_dot(): void
    {
        $this->assertSame('welcome', $this->reverter->resolveLeafKey('welcome'));
    }

    public function test_resolve_leaf_key_returns_last_segment_with_single_dot(): void
    {
        $this->assertSame('welcome', $this->reverter->resolveLeafKey('messages.welcome'));
    }

    public function test_resolve_leaf_key_returns_last_segment_with_multiple_dots(): void
    {
        $this->assertSame('myKey', $this->reverter->resolveLeafKey('a.b.c.myKey'));
    }

    public function test_resolve_leaf_key_trims_whitespace(): void
    {
        $this->assertSame('welcome', $this->reverter->resolveLeafKey('  messages.welcome  '));
    }

    // ------------------------------------------------------------------
    // revert — basic
    // ------------------------------------------------------------------

    public function test_revert_replaces_double_quote_helper(): void
    {
        $result = $this->reverter->revert('<h1>{{ __("welcome") }}</h1>', ['welcome' => 'Bienvenue']);

        $this->assertSame('<h1>Bienvenue</h1>', $result['content']);
        $this->assertSame(['welcome' => 'Bienvenue'], $result['replaced']);
    }

    public function test_revert_replaces_single_quote_helper(): void
    {
        $result = $this->reverter->revert("<p>{{ __('greeting') }}</p>", ['greeting' => 'Bonjour']);

        $this->assertSame('<p>Bonjour</p>', $result['content']);
        $this->assertSame(['greeting' => 'Bonjour'], $result['replaced']);
    }

    public function test_revert_handles_spaces_inside_helper(): void
    {
        $result = $this->reverter->revert('<p>{{ __( "welcome" ) }}</p>', ['welcome' => 'Bienvenue']);

        $this->assertSame('<p>Bienvenue</p>', $result['content']);
    }

    // ------------------------------------------------------------------
    // revert — prefixed keys
    // ------------------------------------------------------------------

    public function test_revert_resolves_single_prefix(): void
    {
        $result = $this->reverter->revert('<span>{{ __("messages.farewell") }}</span>', ['farewell' => 'Au revoir']);

        $this->assertSame('<span>Au revoir</span>', $result['content']);
        $this->assertSame(['farewell' => 'Au revoir'], $result['replaced']);
    }

    public function test_revert_resolves_deep_prefix(): void
    {
        $result = $this->reverter->revert('<span>{{ __("a.b.c.title") }}</span>', ['title' => 'Mon titre']);

        $this->assertSame('<span>Mon titre</span>', $result['content']);
    }

    // ------------------------------------------------------------------
    // revert — unknown / partial
    // ------------------------------------------------------------------

    public function test_revert_leaves_helper_untouched_when_key_not_found(): void
    {
        $result = $this->reverter->revert('<p>{{ __("unknown") }}</p>', ['other' => 'Autre']);

        $this->assertSame('<p>{{ __("unknown") }}</p>', $result['content']);
        $this->assertSame([], $result['replaced']);
    }

    public function test_revert_handles_partial_match(): void
    {
        $result = $this->reverter->revert(
            '<p>{{ __("known") }} {{ __("unknown") }}</p>',
            ['known' => 'Connu']
        );

        $this->assertSame('<p>Connu {{ __("unknown") }}</p>', $result['content']);
        $this->assertSame(['known' => 'Connu'], $result['replaced']);
    }

    public function test_revert_with_empty_translations_leaves_content_intact(): void
    {
        $content = '<h1>{{ __("title") }}</h1>';
        $result  = $this->reverter->revert($content, []);

        $this->assertSame($content, $result['content']);
        $this->assertSame([], $result['replaced']);
    }

    // ------------------------------------------------------------------
    // revert — multiple helpers
    // ------------------------------------------------------------------

    public function test_revert_handles_multiple_helpers_in_content(): void
    {
        $result = $this->reverter->revert(
            '<h1>{{ __("title") }}</h1><p>{{ __("messages.body") }}</p>',
            ['title' => 'Titre', 'body' => 'Contenu']
        );

        $this->assertSame('<h1>Titre</h1><p>Contenu</p>', $result['content']);
        $this->assertSame(['title' => 'Titre', 'body' => 'Contenu'], $result['replaced']);
    }

    // ------------------------------------------------------------------
    // listPhpFiles
    // ------------------------------------------------------------------

    public function test_list_php_files_returns_empty_array_when_directory_does_not_exist(): void
    {
        $this->assertSame([], $this->reverter->listPhpFiles('/nonexistent/path'));
    }

    public function test_list_php_files_returns_filenames_without_extension(): void
    {
        $dir = lang_path('fr');
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/messages.php', "<?php\nreturn [];");
        file_put_contents($dir.'/validation.php', "<?php\nreturn [];");

        $result = $this->reverter->listPhpFiles($dir);

        $this->assertContains('messages', $result);
        $this->assertContains('validation', $result);
    }

    public function test_list_php_files_ignores_non_php_files(): void
    {
        $dir = lang_path('fr');
        @mkdir($dir, 0777, true);
        file_put_contents($dir.'/messages.php', "<?php\nreturn [];");
        file_put_contents($dir.'/readme.txt', 'ignore me');

        $result = $this->reverter->listPhpFiles($dir);

        $this->assertNotContains('readme', $result);
    }

    // ------------------------------------------------------------------
    // loadPhpTranslations
    // ------------------------------------------------------------------

    public function test_load_php_translations_returns_array_from_file(): void
    {
        $dir  = lang_path('fr');
        $path = $dir.'/messages.php';
        @mkdir($dir, 0777, true);
        file_put_contents($path, "<?php\nreturn ['hello' => 'Bonjour'];");

        $result = $this->reverter->loadPhpTranslations($path);

        $this->assertSame(['hello' => 'Bonjour'], $result);
    }

    public function test_load_php_translations_returns_empty_array_when_file_missing(): void
    {
        $this->assertSame([], $this->reverter->loadPhpTranslations('/nonexistent/file.php'));
    }

    // ------------------------------------------------------------------
    // loadJsonTranslations
    // ------------------------------------------------------------------

    public function test_load_json_translations_returns_decoded_array(): void
    {
        @mkdir(lang_path(), 0777, true);
        file_put_contents(lang_path('fr.json'), json_encode(['welcome' => 'Bienvenue']));

        $result = $this->reverter->loadJsonTranslations('fr');

        $this->assertSame(['welcome' => 'Bienvenue'], $result);
    }

    public function test_load_json_translations_returns_empty_array_when_file_missing(): void
    {
        $this->assertSame([], $this->reverter->loadJsonTranslations('xx'));
    }

    // ------------------------------------------------------------------
    // removeKeys — json
    // ------------------------------------------------------------------

    public function test_remove_keys_removes_from_json_and_keeps_others(): void
    {
        @mkdir(lang_path(), 0777, true);
        $path = lang_path('fr.json');
        file_put_contents($path, json_encode(['alpha' => 'Alpha', 'beta' => 'Beta', 'gamma' => 'Gamma']));

        $this->reverter->removeKeys($path, ['alpha', 'gamma'], 'json');

        $remaining = json_decode(file_get_contents($path), true);
        $this->assertArrayNotHasKey('alpha', $remaining);
        $this->assertArrayNotHasKey('gamma', $remaining);
        $this->assertArrayHasKey('beta', $remaining);
    }

    // ------------------------------------------------------------------
    // removeKeys — php
    // ------------------------------------------------------------------

    public function test_remove_keys_removes_from_php_and_keeps_others(): void
    {
        $dir  = lang_path('fr');
        $path = $dir.'/messages.php';
        @mkdir($dir, 0777, true);
        file_put_contents($path, "<?php\nreturn ['alpha' => 'Alpha', 'beta' => 'Beta'];");

        $this->reverter->removeKeys($path, ['alpha'], 'php');

        $remaining = include $path;
        $this->assertArrayNotHasKey('alpha', $remaining);
        $this->assertArrayHasKey('beta', $remaining);
    }

    // ------------------------------------------------------------------
    // buildPhpArrayFile
    // ------------------------------------------------------------------

    public function test_build_php_array_file_produces_valid_php(): void
    {
        $content = $this->reverter->buildPhpArrayFile(['hello' => 'Bonjour', 'bye' => 'Au revoir']);

        $this->assertStringContainsString('<?php', $content);
        $this->assertStringContainsString('"hello" => "Bonjour"', $content);
        $this->assertStringContainsString('"bye" => "Au revoir"', $content);
        $this->assertStringContainsString('return [', $content);
    }

    public function test_build_php_array_file_produces_empty_return_for_empty_array(): void
    {
        $content = $this->reverter->buildPhpArrayFile([]);

        $this->assertStringContainsString('return [', $content);
        $this->assertStringContainsString('];', $content);
        $this->assertStringNotContainsString('=>', $content);
    }
}