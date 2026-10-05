<?php

declare(strict_types=1);

namespace Contenir\Config\Reader;

use Throwable;

use function is_array;
use function is_file;
use function is_readable;

/**
 * Tolerant PHP-array config file reader.
 *
 * Returns an empty array when the file is missing, unreadable, contains a
 * parse error, or `return`s a non-array — so first-run consumers can ask
 * for state before any has been written, without crashing.
 *
 * Mirrors the contract of Laminas\Config\Reader\PhpArray but:
 * - swallows include errors instead of throwing
 * - returns `array` for static analysis instead of `array|string`
 * - is intentionally stateless (static method)
 */
final class PhpArray
{
    /**
     * @return array<array-key, mixed>
     */
    public static function fromFile(string $filename): array
    {
        if (! is_file($filename) || ! is_readable($filename)) {
            return [];
        }

        try {
            return self::arrayOrEmpty(include $filename);
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function arrayOrEmpty(mixed $data): array
    {
        return is_array($data) ? $data : [];
    }
}
