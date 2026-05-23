<?php

namespace Vnuswilliams\LaravelAutoLang\Services;

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
     * Scan directories and collect Blade view files.
     *
     * @param  array<int, string>  $paths
     * @return array<int, string>
     */
    public function scan(array $paths): array
    {
        $result = [];

        foreach ($paths as $path) {
            if (! $this->files->isDirectory($path)) {
                continue;
            }

            foreach ($this->files->allFiles($path) as $file) {
                if (str_ends_with($file->getFilename(), '.blade.php')) {
                    $result[] = $file->getPathname();
                }
            }
        }

        sort($result);

        return $result;
    }
}
