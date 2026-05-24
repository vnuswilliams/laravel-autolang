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
     protected $signature = 'lang:auto {--locale= : Locale to target for translation file (e.g. fr)} {--output= : Output format: json or php} {--dry : Preview changes without writing files} {--force : Skip confirmation prompts}';

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
        $locale = (string) ($this->option('locale') ?: 'en');
        $dryRun = (bool) $this->option('dry');
        $force = (bool) $this->option('force');

        $output = strtolower((string) ($this->option('output') ?: 'json'));
        if (! in_array($output, ['json', 'php'], true)) {
            $this->error('Invalid output format. Allowed values: json, php.');

            return self::FAILURE;
        }

        $bladeFiles = $scanner->scan($paths);

        if ($bladeFiles === []) {
            $this->warn('No Blade files found.');

            return self::SUCCESS;
        }

        $changes = [];
        $allStrings = [];

        foreach ($bladeFiles as $file) {
            $original = $files->get($file);
            $allStrings = [...$allStrings, ...$extractor->extractTranslationKeys($original)];
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

        $allStrings = array_values(array_unique($allStrings));

        if ($changes === [] && $allStrings === []) {
            $this->info('No translatable strings found.');

            return self::SUCCESS;
        }

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

        if ($changes !== []) {
            foreach ($changes as $change) {
                $files->put($change['file'], $change['content']);
            }
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
        $this->line("Translation entries added to {$target}: {$addedCount}");

        return self::SUCCESS;
    }
}
