<?php

namespace Vnuswilliams\LaravelAutoLang\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Vnuswilliams\LaravelAutoLang\Services\BladeScanner;
use Vnuswilliams\LaravelAutoLang\Services\BladeTransformer;
use Vnuswilliams\LaravelAutoLang\Services\JsonTranslationWriter;
use Vnuswilliams\LaravelAutoLang\Services\TextExtractor;

class AutoLangCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
    protected $signature = 'lang:auto {--dry : Preview changes without writing files} {--force : Skip confirmation prompts}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan blade views, wrap hardcoded text with __(), and update JSON translations.';

    /**
     * Execute the console command.
     */
    public function handle(
        Filesystem $files,
        BladeScanner $scanner,
        TextExtractor $extractor,
        BladeTransformer $transformer,
        JsonTranslationWriter $writer
    ): int {
        $paths = (array) config('lang-auto.paths', [resource_path('views')]);
        $locale = (string) config('lang-auto.locale', 'en');
        $dryRun = (bool) $this->option('dry');
        $force = (bool) $this->option('force');

        $bladeFiles = $scanner->scan($paths);

        if ($bladeFiles === []) {
            $this->warn('No Blade files found.');

            return self::SUCCESS;
        }

        $changes = [];
        $allStrings = [];

        foreach ($bladeFiles as $file) {
            $original = $files->get($file);
            $strings = $extractor->extractTranslatableStrings($original);

            if ($strings === []) {
                continue;
            }

            $transformed = $transformer->transform($original, $strings);

            if ($transformed === $original) {
                continue;
            }

            $changes[] = ['file' => $file, 'content' => $transformed, 'strings' => $strings];
            $allStrings = [...$allStrings, ...$strings];
        }

        if ($changes === []) {
            $this->info('No new translatable strings found.');

            return self::SUCCESS;
        }

        $allStrings = array_values(array_unique($allStrings));

        $this->info('Detected strings:');
        foreach ($allStrings as $string) {
            $this->line("- {$string}");
        }

        $this->newLine();
        $this->info('Affected files:');
        foreach ($changes as $change) {
            $this->line('- '.$change['file']);
        }

        if ($dryRun) {
            $this->comment('Dry run complete. No files were modified.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Apply these changes?', true)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        foreach ($changes as $change) {
            $files->put($change['file'], $change['content']);
        }

        $addedCount = $writer->append($locale, $allStrings);

        $this->info('Done.');
        $this->line('Updated Blade files: '.count($changes));
        $this->line("New translation entries added to {$locale}.json: {$addedCount}");

        return self::SUCCESS;
    }
}
