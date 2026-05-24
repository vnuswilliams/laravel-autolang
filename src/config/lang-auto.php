<?php

return [
    /**
     * Directories scanned for Blade templates.
     */
    'paths' => [
        resource_path('views'),
    ],

    /**
     * Allowed view file extensions used during scanning.
     */
    'extensions' => [
        '.blade.php',
    ],

    /**
     * Locale used for generated translation files.
     */
    'locale' => 'en',

    /**
     * Output format for translations: json or php.
     */
    'output' => 'json',

    /**
     * Default file name used when output = php.
     */
    'php_file' => 'messages',
];
