<?php

namespace VnusWilliams\LaravelAutoLang\Services;

use Illuminate\Filesystem\Filesystem;

class BladeScanner
{
    /**
     * Create a new scanner instance.
     */
    public function __construct(private readonly Filesystem $files)
    {
    }

    /**
     * Scan directories and collect view files by extensions.
     *
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    public function scanAll(array $paths, array $extensions): array
    {
        $result = [];
        $normalizedExtensions = $this->normalizeExtensions($extensions);

        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if ($this->matchesExtension($file->getFilename(), $normalizedExtensions)) {
                    $result[] = $file->getPathname();
                }
            }
        }

        sort($result);

        return $result;
    }

    /**
     * Resolve a relative file path against configured root paths.
     *
     * @param  array<int, string>  $paths
     * @param  array<int, string>  $extensions
     */
    public function findByRelativePath(string $relativePath, array $paths, array $extensions): ?string
    {
        $normalizedExtensions = $this->normalizeExtensions($extensions);
        $sanitizedPath = ltrim(trim($relativePath), '/\\');
        $sanitizedPath = preg_replace('#[\\/]+#', DIRECTORY_SEPARATOR, $sanitizedPath) ?? $sanitizedPath;

        if ($sanitizedPath === '') {
            return null;
        }

        $hasAllowedExtension = $this->matchesExtension($sanitizedPath, $normalizedExtensions);

        foreach ($paths as $root) {
            if (! $this->files->isDirectory($root)) {
                continue;
            }

            if ($hasAllowedExtension) {
                $candidate = rtrim($root, '/\\').DIRECTORY_SEPARATOR.$sanitizedPath;
                if ($this->files->exists($candidate)) {
                    return $candidate;
                }
            }

            foreach ($normalizedExtensions as $extension) {
                $candidate = rtrim($root, '/\\').DIRECTORY_SEPARATOR.$sanitizedPath.$extension;
                if ($this->files->exists($candidate)) {
                    return $candidate;
                }
            }
        }

        return null;
    }

    /**
     * @param  array<int, string>  $extensions
     * @return array<int, string>
     */
    private function normalizeExtensions(array $extensions): array
    {
        $normalized = array_values(array_filter(array_map(static function (string $extension): string {
            $trimmed = trim($extension);
            if ($trimmed === '') {
                return '';
            }

            return str_starts_with($trimmed, '.') ? $trimmed : '.'.$trimmed;
        }, $extensions)));

        return $normalized === [] ? ['.blade.php'] : $normalized;
    }

    /**
     * @param  array<int, string>  $extensions
     */
    private function matchesExtension(string $fileName, array $extensions): bool
    {
        foreach ($extensions as $extension) {
            if (str_ends_with($fileName, $extension)) {
                return true;
            }
        }

        return false;
    }
}
