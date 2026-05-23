<?php

return [
    /**
     * Directories scanned for Blade templates.
     */
    'paths' => [
        resource_path('views'),
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
