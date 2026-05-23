<?php

namespace Vnuswilliams\LaravelAutoLang\Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Vnuswilliams\LaravelAutoLang\Tests\TestCase;

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

    public function test_force_mode_updates_blade_and_json_file(): void
    {
        $viewsPath = resource_path('views');
        $langPath = lang_path();

        @mkdir($viewsPath, 0777, true);
        @mkdir($langPath, 0777, true);

        $bladeFile = $viewsPath.'/home.blade.php';
        file_put_contents($bladeFile, "<p>Bonjour</p>");

        $exit = Artisan::call('lang:auto', ['--force' => true, '--locale' => 'fr']);
        $output = Artisan::output();

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('Done.', $output);
        $this->assertSame("<p>{{ __('Bonjour') }}</p>", file_get_contents($bladeFile));

        $json = json_decode((string) file_get_contents(lang_path('fr.json')), true);
        $this->assertIsArray($json);
        $this->assertArrayHasKey('Bonjour', $json);
        $this->assertSame('Bonjour', $json['Bonjour']);
    }
}
