<?php

namespace VnusWilliams\LaravelAutoLang\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use VnusWilliams\LaravelAutoLang\Tests\TestCase;

class AutoLangCommandTest extends TestCase
{
    // ==================================================================
    // FORWARD tests (existing behaviour)
    // ==================================================================

    public function test_dry_run_detects_text_without_modifying_files(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path(), 0777, true);

        $bladeFile = $viewsPath.'/welcome.blade.php';
        file_put_contents($bladeFile, "<h1>Welcome</h1>");

        $exit   = Artisan::call('lang:auto', ['path' => 'welcome', '--dry' => true, '--force' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Detected strings:', $output);
        $this->assertStringContainsString('Dry run complete. No files were modified.', $output);
        $this->assertSame("<h1>Welcome</h1>", file_get_contents($bladeFile));
        $this->assertFileDoesNotExist(lang_path('en.json'));
    }

    public function test_default_locale_and_output_are_used_when_options_missing(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);

        file_put_contents($viewsPath.'/home.blade.php', "<p>Hello</p>");

        config()->set('lang-auto.locale', 'fr');
        config()->set('lang-auto.output', 'php');

        $exit = Artisan::call('lang:auto', ['path' => 'home', '--force' => true]);

        $this->assertSame(0, $exit);
        $this->assertFileExists(lang_path('fr/home.php'));
        $this->assertFileDoesNotExist(lang_path('en.json'));
    }

    public function test_php_file_name_is_derived_from_blade_file_name(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        file_put_contents($viewsPath.'/employees.blade.php', "<p>Create employee</p>");

        $exit = Artisan::call('lang:auto', ['path' => 'employees', '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        $phpTranslations = include lang_path('fr/employees.php');
        $this->assertIsArray($phpTranslations);
        $this->assertArrayHasKey('createemployee', $phpTranslations);
        $this->assertSame('Create employee', $phpTranslations['createemployee']);
    }

    public function test_php_file_name_strips_special_chars_and_lowercases(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath.'/hr', 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        file_put_contents($viewsPath.'/hr/leave-balance.blade.php', "<p>Leave balance</p>");

        $exit = Artisan::call('lang:auto', ['path' => 'hr/leave-balance', '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        $this->assertFileExists(lang_path('fr/leavebalance.php'));
    }

    public function test_relative_path_works_for_nested_view_without_extension(): void
    {
        $viewsPath = resource_path('views/pages');
        @mkdir($viewsPath, 0777, true);

        $bladeFile = $viewsPath.'/welcome.blade.php';
        file_put_contents($bladeFile, "<h2>Hello page</h2>");

        $exit   = Artisan::call('lang:auto', ['path' => 'pages/welcome', '--dry' => true, '--force' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString($bladeFile, $output);
    }

    public function test_all_option_scans_everything(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath.'/pages', 0777, true);

        file_put_contents($viewsPath.'/one.blade.php', "<p>One</p>");
        file_put_contents($viewsPath.'/pages/two.blade.php', "<p>Two</p>");

        $exit   = Artisan::call('lang:auto', ['--all' => true, '--dry' => true, '--force' => true]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('one.blade.php', $output);
        $this->assertStringContainsString('two.blade.php', $output);
    }

    public function test_all_option_writes_one_php_file_per_blade_file(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath.'/pages', 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        file_put_contents($viewsPath.'/invoice.blade.php', "<p>Invoice total</p>");
        file_put_contents($viewsPath.'/pages/dashboard.blade.php', "<p>Dashboard title</p>");

        $exit = Artisan::call('lang:auto', ['--all' => true, '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        $this->assertFileExists(lang_path('fr/invoice.php'));
        $this->assertFileExists(lang_path('fr/dashboard.php'));
    }

    public function test_rerun_does_not_duplicate_prefixed_php_keys(): void
    {
        // Simulate a blade that was already processed on a first run:
        // the text is wrapped, and the translation file already contains the key.
        // A second run must not add a duplicate entry.
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        // Blade already wrapped — extractTranslatableStrings() will find nothing new.
        file_put_contents($viewsPath.'/prefixed.blade.php', '<p>{{ __("prefixed.lacle") }}</p>');

        // Pre-existing translation file as written by the first run.
        file_put_contents(lang_path('fr/prefixed.php'), "<?php\nreturn ['lacle' => 'lacle'];\n");

        $exit = Artisan::call('lang:auto', ['path' => 'prefixed', '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);

        // File must still contain exactly one entry — no duplication.
        $phpTranslations = include lang_path('fr/prefixed.php');
        $this->assertIsArray($phpTranslations);
        $this->assertSame(['lacle' => 'lacle'], $phpTranslations);
    }

    public function test_rerun_does_not_duplicate_prefixed_json_keys(): void
    {
        // Same logic for JSON: blade already wrapped, key already present.
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path(), 0777, true);

        file_put_contents($viewsPath.'/prefixed-json.blade.php', '<p>{{ __("prefix.unautreprefix.lacle") }}</p>');

        // Pre-existing JSON as written by the first run.
        file_put_contents(lang_path('fr.json'), json_encode(['lacle' => 'lacle'], JSON_PRETTY_PRINT).PHP_EOL);

        $exit = Artisan::call('lang:auto', ['path' => 'prefixed-json', '--force' => true, '--locale' => 'fr', '--output' => 'json']);

        $this->assertSame(0, $exit);

        $jsonTranslations = json_decode((string) file_get_contents(lang_path('fr.json')), true);
        $this->assertIsArray($jsonTranslations);
        $this->assertSame(['lacle' => 'lacle'], $jsonTranslations);
    }

    // ==================================================================
    // REVERSE tests
    // ==================================================================

    public function test_reverse_replaces_json_helper_with_raw_value_and_removes_key(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);

        $bladeFile = $viewsPath.'/rev-json.blade.php';
        file_put_contents($bladeFile, '<h1>{{ __("welcome") }}</h1>');

        $langFile = lang_path('fr.json');
        @mkdir(lang_path(), 0777, true);
        file_put_contents($langFile, json_encode(['welcome' => 'Bienvenue', 'other' => 'Autre']));

        $exit = Artisan::call('lang:auto', [
            'path'      => 'rev-json',
            '--reverse' => true,
            '--locale'  => 'fr',
            '--output'  => 'json',
            '--force'   => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame('<h1>Bienvenue</h1>', file_get_contents($bladeFile));

        $remaining = json_decode(file_get_contents($langFile), true);
        $this->assertArrayNotHasKey('welcome', $remaining);
        $this->assertArrayHasKey('other', $remaining);
    }

    public function test_reverse_replaces_php_helper_with_raw_value_and_removes_key(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        $bladeFile = $viewsPath.'/rev-php.blade.php';
        file_put_contents($bladeFile, '<p>{{ __("rev-php.greeting") }}</p>');

        $langFile = lang_path('fr/revphp.php');
        file_put_contents($langFile, "<?php\nreturn ['greeting' => 'Bonjour', 'bye' => 'Au revoir'];\n");

        $exit = Artisan::call('lang:auto', [
            'path'      => 'rev-php',
            '--reverse' => true,
            '--locale'  => 'fr',
            '--output'  => 'php',
            '--force'   => true,
        ]);

        $this->assertSame(0, $exit);
        $this->assertSame('<p>Bonjour</p>', file_get_contents($bladeFile));

        $remaining = include $langFile;
        $this->assertArrayNotHasKey('greeting', $remaining);
        $this->assertArrayHasKey('bye', $remaining);
    }

    public function test_reverse_dry_run_does_not_modify_files(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path(), 0777, true);

        $bladeFile = $viewsPath.'/rev-dry.blade.php';
        file_put_contents($bladeFile, '<h1>{{ __("title") }}</h1>');

        $langFile = lang_path('fr.json');
        file_put_contents($langFile, json_encode(['title' => 'Titre']));

        $exit   = Artisan::call('lang:auto', [
            'path'      => 'rev-dry',
            '--reverse' => true,
            '--locale'  => 'fr',
            '--output'  => 'json',
            '--dry'     => true,
            '--force'   => true,
        ]);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Dry run complete. No files were modified.', $output);
        $this->assertSame('<h1>{{ __("title") }}</h1>', file_get_contents($bladeFile));
        $this->assertStringContainsString('title', file_get_contents($langFile));
    }

    public function test_reverse_resolves_prefixed_key_in_json(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path(), 0777, true);

        $bladeFile = $viewsPath.'/rev-prefix.blade.php';
        file_put_contents($bladeFile, '<p>{{ __("messages.farewell") }}</p>');

        $langFile = lang_path('fr.json');
        file_put_contents($langFile, json_encode(['farewell' => 'Au revoir']));

        Artisan::call('lang:auto', [
            'path'      => 'rev-prefix',
            '--reverse' => true,
            '--locale'  => 'fr',
            '--output'  => 'json',
            '--force'   => true,
        ]);

        $this->assertSame('<p>Au revoir</p>', file_get_contents($bladeFile));
    }

    public function test_reverse_leaves_unknown_keys_untouched(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path(), 0777, true);

        $bladeFile = $viewsPath.'/rev-unknown.blade.php';
        $original  = '<p>{{ __("notinfile") }}</p>';
        file_put_contents($bladeFile, $original);

        $langFile = lang_path('fr.json');
        file_put_contents($langFile, json_encode(['other' => 'Autre']));

        Artisan::call('lang:auto', [
            'path'      => 'rev-unknown',
            '--reverse' => true,
            '--locale'  => 'fr',
            '--output'  => 'json',
            '--force'   => true,
        ]);

        $this->assertSame($original, file_get_contents($bladeFile));
    }

    public function test_reverse_all_processes_multiple_blade_files(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath.'/rev', 0777, true);
        @mkdir(lang_path(), 0777, true);

        file_put_contents($viewsPath.'/rev/a.blade.php', '<h1>{{ __("alpha") }}</h1>');
        file_put_contents($viewsPath.'/rev/b.blade.php', '<p>{{ __("beta") }}</p>');

        $langFile = lang_path('fr.json');
        file_put_contents($langFile, json_encode(['alpha' => 'Alpha FR', 'beta' => 'Bêta FR']));

        Artisan::call('lang:auto', [
            '--all'     => true,
            '--reverse' => true,
            '--locale'  => 'fr',
            '--output'  => 'json',
            '--force'   => true,
        ]);

        $this->assertSame('<h1>Alpha FR</h1>', file_get_contents($viewsPath.'/rev/a.blade.php'));
        $this->assertSame('<p>Bêta FR</p>', file_get_contents($viewsPath.'/rev/b.blade.php'));

        $remaining = json_decode(file_get_contents($langFile), true);
        $this->assertArrayNotHasKey('alpha', $remaining);
        $this->assertArrayNotHasKey('beta', $remaining);
    }
}