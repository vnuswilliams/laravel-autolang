<?php

namespace VnusWilliams\LaravelAutoLang\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use VnusWilliams\LaravelAutoLang\Services\BladeScanner;
use VnusWilliams\LaravelAutoLang\Services\BladeTransformer;
use VnusWilliams\LaravelAutoLang\Services\TranslationWriter;
use VnusWilliams\LaravelAutoLang\Services\TextExtractor;

class AutoLangCommand extends Command
{
    /**
     * The console command signature.
     *
     * @var string
     */
     protected $signature = 'lang:auto {path? : Relative path from configured view root(s), without extension} {--all : Scan all files from configured view root(s)} {--locale= : Locale to target for translation file (e.g. fr)} {--output= : Output format: json or php} {--dry : Preview changes without writing files} {--force : Skip confirmation prompts}';

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
        TranslationWriter $writer
    ): int {
        $paths = (array) config('lang-auto.paths', [resource_path('views')]);
        $extensions = (array) config('lang-auto.extensions', ['.blade.php']);
        $locale = (string) ($this->option('locale') ?: config('lang-auto.locale', 'en'));
        $dryRun = (bool) $this->option('dry');
        $force = (bool) $this->option('force');
        $scanAll = (bool) $this->option('all');

        $output = strtolower((string) ($this->option('output') ?: config('lang-auto.output', 'json')));
        if (! in_array($output, ['json', 'php'], true)) {
            $this->error('Invalid output format. Allowed values: json, php.');

            return self::FAILURE;
        }

        $inputPath = (string) ($this->argument('path') ?? '');
        $inputPath = ltrim(trim($inputPath), '/\\');

        if (! $scanAll && $inputPath === '') {
            $inputPath = ltrim((string) $this->ask('Enter the relative file path from resources/views (without leading slash and without extension)'), '/\\');
        }

        if ($scanAll && ! $force) {
            $this->warn('You are about to scan every matching view file in the configured directories, including subdirectories.');

            if (! $this->confirm('Continue with --all scan?', false)) {
                $this->warn('Operation cancelled.');

                return self::SUCCESS;
            }
        }

        $bladeFiles = $scanAll
            ? $scanner->scanAll($paths, $extensions)
            : array_values(array_filter([(string) $scanner->findByRelativePath($inputPath, $paths, $extensions)]));

        if ($bladeFiles === []) {
            $this->warn($scanAll ? 'No matching view files found.' : 'View file not found for the provided path.');

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

        $phpFile = null;
        if ($output === 'php') {
            $defaultPhpFile = (string) config('lang-auto.php_file', 'messages');
            $phpFile = $force
                ? $defaultPhpFile
                : (string) $this->ask('Translation file name (without .php)', $defaultPhpFile);
        }

        $addedCount = $writer->append($locale, $allStrings, $output, $phpFile);

        $target = $output === 'json'
            ? "{$locale}.json"
            : $locale.'/'.trim((string) preg_replace('/\.php$/i', '', (string) $phpFile)).'.php';

        $this->info('Done.');
        $this->line('Updated Blade files: '.count($changes));
        $this->line("New translation entries added to {$target}: {$addedCount}");

        return self::SUCCESS;
    }
}
