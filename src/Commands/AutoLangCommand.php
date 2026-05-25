<?php

namespace VnusWilliams\LaravelAutoLang\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use VnusWilliams\LaravelAutoLang\Services\BladeReverter;
use VnusWilliams\LaravelAutoLang\Services\BladeScanner;
use VnusWilliams\LaravelAutoLang\Services\BladeTransformer;
use VnusWilliams\LaravelAutoLang\Services\TextExtractor;
use VnusWilliams\LaravelAutoLang\Services\TranslationWriter;

class AutoLangCommand extends Command
{
    protected $signature = 'lang:auto
        {path? : Relative path from configured view root(s), without extension}
        {--all : Scan all files from configured view root(s)}
        {--locale= : Locale to target for translation file (e.g. fr)}
        {--output= : Output format: json or php}
        {--dry : Preview changes without writing files}
        {--force : Skip confirmation prompts}
        {--reverse : Replace __() helpers with raw translation values and clean up the translation file}';

    protected $description = 'Scan blade views, wrap hardcoded text with __(), and update translations. Use --reverse to undo.';

    public function handle(
        Filesystem $files,
        BladeScanner $scanner,
        TextExtractor $extractor,
        BladeTransformer $transformer,
        TranslationWriter $writer,
        BladeReverter $reverter
    ): int {
        $paths      = (array) config('lang-auto.paths', [resource_path('views')]);
        $extensions = (array) config('lang-auto.extensions', ['.blade.php']);
        $locale     = (string) ($this->option('locale') ?: config('lang-auto.locale', 'en'));
        $dryRun     = (bool) $this->option('dry');
        $force      = (bool) $this->option('force');
        $scanAll    = (bool) $this->option('all');
        $reverse    = (bool) $this->option('reverse');

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

        return $reverse
            ? $this->handleReverse($files, $reverter, $bladeFiles, $locale, $output, $dryRun, $force)
            : $this->handleForward($files, $extractor, $transformer, $writer, $bladeFiles, $locale, $output, $dryRun, $force);
    }

    // ====================================================================
    // FORWARD
    // ====================================================================

    private function handleForward(
        Filesystem $files,
        TextExtractor $extractor,
        BladeTransformer $transformer,
        TranslationWriter $writer,
        array $bladeFiles,
        string $locale,
        string $output,
        bool $dryRun,
        bool $force
    ): int {
        $changes    = [];
        $allStrings = [];

        foreach ($bladeFiles as $file) {
            $phpFile  = $output === 'php' ? $this->derivePhpFileName($file) : null;
            $original = $files->get($file);

            $allStrings = [...$allStrings, ...$extractor->extractTranslationKeys($original)];
            $strings    = $extractor->extractTranslatableStrings($original);

            if ($strings === []) {
                continue;
            }

            $transformed = $transformer->transform($original, $strings, $phpFile, $output);

            if ($transformed === $original) {
                continue;
            }

            $changes[]  = ['file' => $file, 'content' => $transformed, 'strings' => $strings, 'phpFile' => $phpFile];
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
            $label = $change['file'];
            if ($output === 'php') {
                $label .= "  →  {$locale}/{$change['phpFile']}.php";
            }
            $this->line('- '.$label);
        }

        if ($dryRun) {
            $this->comment('Dry run complete. No files were modified.');

            return self::SUCCESS;
        }

        if (! $force && ! $this->confirm('Apply these changes?', true)) {
            $this->warn('Operation cancelled.');

            return self::SUCCESS;
        }

        $addedCount = 0;
        foreach ($changes as $change) {
            $files->put($change['file'], $change['content']);
            $addedCount += $writer->append($locale, $change['strings'], $output, $change['phpFile']);
        }

        $this->info('Done.');
        $this->line('Updated Blade files: '.count($changes));
        $this->line("Translation entries added: {$addedCount}");

        return self::SUCCESS;
    }

    // ====================================================================
    // REVERSE
    // ====================================================================

    private function handleReverse(
        Filesystem $files,
        BladeReverter $reverter,
        array $bladeFiles,
        string $locale,
        string $output,
        bool $dryRun,
        bool $force
    ): int {
        [$translationPath, $translations] = $output === 'php'
            ? $this->pickPhpTranslationFile($reverter, $locale)
            : $this->resolveJsonTranslationFile($reverter, $locale);

        if ($translationPath === null) {
            $this->warn('No translation file selected or found. Aborting.');

            return self::SUCCESS;
        }

        if ($translations === []) {
            $this->warn("Translation file is empty: {$translationPath}");

            return self::SUCCESS;
        }

        $changes  = [];
        $usedKeys = [];

        foreach ($bladeFiles as $file) {
            $result = $reverter->revert($files->get($file), $translations);

            if ($result['replaced'] === []) {
                continue;
            }

            $changes[]  = ['file' => $file, 'content' => $result['content'], 'replaced' => $result['replaced']];
            $usedKeys   = array_merge($usedKeys, array_keys($result['replaced']));
        }

        $usedKeys = array_values(array_unique($usedKeys));

        if ($changes === []) {
            $this->info('No translation helpers found to revert.');

            return self::SUCCESS;
        }

        $this->info('Keys to revert:');
        foreach ($usedKeys as $key) {
            $this->line("- {$key}  →  \"{$translations[$key]}\"");
        }

        $this->newLine();
        $this->info('Affected Blade files:');
        foreach ($changes as $change) {
            $this->line('- '.$change['file']);
        }
        $this->line("Translation file: {$translationPath}");

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

        $reverter->removeKeys($translationPath, $usedKeys, $output);

        $this->info('Done.');
        $this->line('Updated Blade files: '.count($changes));
        $this->line('Keys removed from translation file: '.count($usedKeys));

        return self::SUCCESS;
    }

    // ====================================================================
    // Interactive translation file resolution (reverse only)
    // ====================================================================

    /**
     * List PHP files in lang/<locale>/ and let the user pick one.
     *
     * @return array{0: string|null, 1: array<string, string>}
     */
    private function pickPhpTranslationFile(BladeReverter $reverter, string $locale): array
    {
        $langDir = lang_path($locale);
        $choices = $reverter->listPhpFiles($langDir);

        if ($choices === []) {
            $this->warn("No PHP translation files found in: {$langDir}");

            return [null, []];
        }

        $chosen = count($choices) === 1
            ? $choices[0]
            : (string) $this->choice('Which translation file do you want to use?', $choices, 0);

        $path = $langDir.DIRECTORY_SEPARATOR.$chosen.'.php';

        return [$path, $reverter->loadPhpTranslations($path)];
    }

    /**
     * Resolve the JSON translation file path and load it.
     *
     * @return array{0: string|null, 1: array<string, string>}
     */
    private function resolveJsonTranslationFile(BladeReverter $reverter, string $locale): array
    {
        $path         = lang_path("{$locale}.json");
        $translations = $reverter->loadJsonTranslations($locale);

        if ($translations === [] && ! file_exists($path)) {
            $this->warn("JSON translation file not found: {$path}");

            return [null, []];
        }

        return [$path, $translations];
    }

    // ====================================================================
    // Forward-only helper
    // ====================================================================

    /**
     * Derive the PHP translation file name from a Blade file path.
     *
     * Rules:
     *  - Strip all extensions (.blade.php → two passes)
     *  - Lowercase
     *  - Remove every character that is not a Unicode letter or digit
     *
     * Examples:
     *  welcome.blade.php       → welcome
     *  ⚡welcome-to.blade.php  → welcometo
     *  My_Cool View.blade.php  → mycoolview
     */
    private function derivePhpFileName(string $filePath): string
    {
        $name = basename($filePath);

        while (str_contains($name, '.')) {
            $name = pathinfo($name, PATHINFO_FILENAME);
        }

        $name = mb_strtolower($name, 'UTF-8');
        $name = (string) preg_replace('/[^\p{L}\p{N}]/u', '', $name);

        return $name !== '' ? $name : 'messages';
    }
}