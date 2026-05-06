<?php

declare(strict_types=1);

namespace Contenir\Config\Reader;

use Throwable;

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
            /** @psalm-suppress UnresolvableInclude */
            $data = include $filename;
        } catch (Throwable) {
            return [];
        }

        return is_array($data) ? $data : [];
    }
}
