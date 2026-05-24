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

        $exit = Artisan::call('lang:auto', ['--dry' => true, '--force' => true]);
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

        $exit = Artisan::call('lang:auto', ['--force' => true]);

        $this->assertSame(0, $exit);
        $this->assertFileExists(lang_path('en.json'));
        $this->assertFileDoesNotExist(lang_path('fr/messages.php'));
    }

    public function test_force_mode_updates_blade_and_php_file_with_camel_case_name_and_collision_key(): void
    {
        $viewsPath = resource_path('views');
        @mkdir($viewsPath, 0777, true);
        @mkdir(lang_path('fr'), 0777, true);

        $bladeFile = $viewsPath.'/employees.blade.php';
        file_put_contents($bladeFile, "<p>Create employee</p>");

        config()->set('lang-auto.php_file', 'my-file 🚀 name');

        file_put_contents(lang_path('fr/myFileName.php'), "<?php\n\nreturn [\n    'myFileName.create' => 'Create',\n];\n");

        $exit = Artisan::call('lang:auto', ['--force' => true, '--locale' => 'fr', '--output' => 'php']);

        $this->assertSame(0, $exit);
        $phpTranslations = include lang_path('fr/myFileName.php');
        $this->assertIsArray($phpTranslations);
        $this->assertArrayHasKey('myFileName.create', $phpTranslations);
        $this->assertArrayHasKey('myFileName.createemployee', $phpTranslations);
        $this->assertSame('Create employee', $phpTranslations['myFileName.createemployee']);
    }
}
