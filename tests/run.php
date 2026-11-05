<?php
/**
 * Runs every *Test.php under tests/Unit. Usage: php tests/run.php
 */
declare(strict_types=1);

require dirname(__DIR__) . '/src/autoload.php';

$files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(__DIR__ . '/Unit'));
$failures = 0;
$tests = 0;
$assertions = 0;

foreach ($files as $file) {
    if (!str_ends_with($file->getFilename(), 'Test.php')) {
        continue;
    }
    $relative = substr($file->getPathname(), strlen(__DIR__) + 1, -4);
    $class = 'Cheppi\\Sellers\\Tests\\' . str_replace('/', '\\', $relative);
    $reflection = new ReflectionClass($class);
    foreach ($reflection->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
        if (!str_starts_with($method->getName(), 'test')) {
            continue;
        }
        $tests++;
        $instance = new $class();
        try {
            $instance->{$method->getName()}();
            echo '.';
        } catch (Throwable $e) {
            $failures++;
            echo "\nFALLA {$class}::{$method->getName()} — {$e->getMessage()}\n";
        }
        $assertions += $instance->assertions;
    }
}

echo "\n\n{$tests} pruebas, {$assertions} comprobaciones, {$failures} fallas\n";
exit($failures > 0 ? 1 : 0);
