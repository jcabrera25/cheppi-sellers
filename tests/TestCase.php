<?php
declare(strict_types=1);

namespace Cheppi\Sellers\Tests;

use RuntimeException;
use Throwable;

/**
 * Minimal assertion base so domain tests run with plain PHP (no network needed).
 * Method names mirror PHPUnit so the suite can move to PHPUnit unchanged later.
 */
abstract class TestCase
{
    public int $assertions = 0;

    protected function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            throw new RuntimeException(sprintf(
                "%sEsperado %s, obtenido %s",
                $message !== '' ? $message . ': ' : '',
                var_export($expected, true),
                var_export($actual, true)
            ));
        }
    }

    protected function assertTrue(bool $condition, string $message = ''): void
    {
        $this->assertSame(true, $condition, $message);
    }

    protected function assertFalse(bool $condition, string $message = ''): void
    {
        $this->assertSame(false, $condition, $message);
    }

    /**
     * @param class-string<Throwable> $class
     * @return Throwable the caught exception, for further checks
     */
    protected function assertThrows(string $class, callable $fn): Throwable
    {
        $this->assertions++;
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $class) {
                return $e;
            }
            throw new RuntimeException(sprintf('Se esperaba %s, se lanzó %s: %s', $class, $e::class, $e->getMessage()));
        }
        throw new RuntimeException(sprintf('Se esperaba %s y no se lanzó nada', $class));
    }
}
