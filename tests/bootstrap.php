<?php

declare(strict_types=1);

// Prefer Composer's autoloader when dependencies are installed.
$composer = __DIR__ . '/../vendor/autoload.php';
if (is_file($composer)) {
    require $composer;
    return;
}

// Fallback PSR-4 autoloader so the suite runs against a standalone phpunit.phar
// without a composer install.
spl_autoload_register(static function (string $class): void {
    $prefixes = [
        'WayaPay\\Tests\\' => __DIR__ . '/',
        'WayaPay\\' => __DIR__ . '/../src/',
    ];
    foreach ($prefixes as $prefix => $base) {
        if (str_starts_with($class, $prefix)) {
            $rel = str_replace('\\', '/', substr($class, strlen($prefix)));
            $file = $base . $rel . '.php';
            if (is_file($file)) {
                require $file;
            }
            return;
        }
    }
});
