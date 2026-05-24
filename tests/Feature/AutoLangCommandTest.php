<?php

namespace VnusWilliams\LaravelAutoLang\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use VnusWilliams\LaravelAutoLang\Tests\TestCase;

class AutoLangCommandTest extends TestCase
{
    public function test_dry_run_detects_text_without_modifying_files(): void
    {
        $viewsPath = resource_path('views');
        $langPath = lang_path();

        @mkdir($viewsPath, 0777, true);
        @mkdir($langPath, 0777, true);

        $bladeFile = $viewsPath.'/welcome.blade.php';
        file_put_contents($bladeFile, "<h1>Welcome</h1>");

        $exit = Artisan::call('lang:auto', ['path' => 'welcome', '--dry' => true, '--force' => true]);
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

        $bladeFile = $viewsPath.'/home.blade.php';
        file_put_contents($bladeFile, "<p>Hello</p>");

        config()->set('lang-auto.locale', 'fr');
        config()->set('lang-auto.output', 'php');

        $exit = Artisan::call('lang:auto', ['path' => 'home', '--force' => true]);

        $this->assertSame(0, $exit);
        // PHP file name is derived from the blade file: home.blade.php → home.php
        $this->assertFileExists(lang_path('fr/home.php'));
        $this->assertFileDoesNotExist(lang_path('en.json'));
    }

    public function test_php_file_name_is_derived_from_blade_file_name(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);

        $bladeFile = $viewsPath.'/employees.blade.php';
        file_put_contents($bladeFile, "<p>Create employee</p>");

        @mkdir(lang_path('fr'), 0777, true);

        $exit = Artisan::call('lang:auto', ['path' => 'employees', '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        // employees.blade.php → fr/employees.php
        $phpTranslations = include lang_path('fr/employees.php');
        $this->assertIsArray($phpTranslations);
        $this->assertArrayHasKey('createemployee', $phpTranslations);
        $this->assertSame('Create employee', $phpTranslations['createemployee']);
    }

    public function test_php_file_name_strips_special_chars_and_lowercases(): void
    {
        $viewsPath = resource_path('views');
        // Simulate a file with dashes and uppercase (filesystem name is ASCII-safe)
        @mkdir($viewsPath.'/hr', 0777, true);

        $bladeFile = $viewsPath.'/hr/leave-balance.blade.php';
        file_put_contents($bladeFile, "<p>Leave balance</p>");

        @mkdir(lang_path('fr'), 0777, true);

        $exit = Artisan::call('lang:auto', ['path' => 'hr/leave-balance', '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        // leave-balance.blade.php → leave-balance (strip exts) → leavebalance (strip dash, lowercase)
        $this->assertFileExists(lang_path('fr/leavebalance.php'));
    }

    public function test_relative_path_works_for_nested_view_without_extension(): void
    {
        $viewsPath = resource_path('views/pages');
        @mkdir($viewsPath, 0777, true);

        $bladeFile = $viewsPath.'/welcome.blade.php';
        file_put_contents($bladeFile, "<h2>Hello page</h2>");

        $exit = Artisan::call('lang:auto', ['path' => 'pages/welcome', '--dry' => true, '--force' => true]);
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

        $exit = Artisan::call('lang:auto', ['--all' => true, '--dry' => true, '--force' => true]);
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
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        $bladeFile = $viewsPath.'/prefixed.blade.php';
        file_put_contents($bladeFile, '<p>{{ __("prefixed.lacle") }}</p>');

        $exit = Artisan::call('lang:auto', ['path' => 'prefixed', '--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        // prefixed.blade.php → fr/prefixed.php
        $phpTranslations = include lang_path('fr/prefixed.php');
        $this->assertIsArray($phpTranslations);
        $this->assertSame(['lacle' => 'lacle'], $phpTranslations);
    }

    public function test_rerun_does_not_duplicate_prefixed_json_keys(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);

        $bladeFile = $viewsPath.'/prefixed-json.blade.php';
        file_put_contents($bladeFile, '<p>{{ __("prefix.unautreprefix.lacle") }}</p>');

        $exit = Artisan::call('lang:auto', ['path' => 'prefixed-json', '--force' => true, '--locale' => 'fr', '--output' => 'json']);

        $this->assertSame(0, $exit);
        $jsonTranslations = json_decode((string) file_get_contents(lang_path('fr.json')), true);
        $this->assertIsArray($jsonTranslations);
        $this->assertSame(['lacle' => 'lacle'], $jsonTranslations);
    }
}