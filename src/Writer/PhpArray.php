<?php

declare(strict_types=1);

namespace Contenir\Config\Writer;

use Contenir\Config\Exception\WriteException;
use Webimpress\SafeWriter\Exception\ExceptionInterface as SafeWriterException;
use Webimpress\SafeWriter\FileWriter;

use function array_is_list;
use function array_keys;
use function array_map;
use function dirname;
use function function_exists;
use function implode;
use function is_array;
use function is_dir;
use function is_int;
use function is_writable;
use function mkdir;
use function opcache_invalidate;
use function restore_error_handler;
use function set_error_handler;
use function sprintf;
use function str_repeat;
use function var_export;

/**
 * PHP-array config file writer.
 *
 * Adapted from Laminas\Config\Writer\PhpArray. Differences from the original:
 *
 * - Defaults to PHP 5.4+ short-array syntax (`[]`).
 * - Persists via webimpress/safe-writer so writes are serialised across
 *   processes (flock on a sidecar lock file) and atomically swapped into
 *   place — partial writes are never visible to readers, and concurrent
 *   writers don't race during the temp-write/rename window.
 * - Invalidates the file's cached opcode after a successful write so
 *   long-running PHP-FPM workers don't serve stale state.
 * - Drops FQN-to-classname-scalar resolution, Windows path escaping, and
 *   the AbstractWriter inheritance — none are needed by Contenir consumers.
 * - Throws Contenir\Config\Exception\WriteException with a per-package
 *   `label` so error messages reflect the consumer's domain
 *   ("error pages", "maintenance state") rather than generic file talk.
 */
final class PhpArray
{
    private const string INDENT = '    ';

    /**
     * Recursively render any PHP value as source using short-array syntax.
     *
     * Round-trips scalars, null, and arbitrarily-nested arrays — necessary
     * because consumers may preserve operator-authored data in keys they
     * don't manage.
     *
     * @param positive-int $indent
     */
    public static function exportArray(mixed $value, int $indent = 1): string
    {
        if (! is_array($value)) {
            return var_export(
                value: $value,
                return: true,
            );
        }

        if ([] === $value) {
            return '[]';
        }

        $isList   = array_is_list($value);
        $pad      = str_repeat(self::INDENT, $indent);
        $closePad = str_repeat(self::INDENT, $indent - 1);

        $lines = array_map(
            static fn(int|string $key, mixed $child): string => $isList
                ? sprintf('%s%s,', $pad, self::exportArray($child, $indent + 1))
                : sprintf('%s%s => %s,', $pad, self::exportKey($key), self::exportArray($child, $indent + 1)),
            array_keys($value),
            $value,
        );

        return implode("\n", ['[', ...$lines, "{$closePad}]"]);
    }

    /**
     * Render a PHP-array config to source. Returns the full file contents
     * including the `<?php` preamble and trailing semicolon.
     *
     * @param array<array-key, mixed> $config
     */
    public static function processConfig(array $config): string
    {
        return "<?php\n\nreturn " . self::exportArray($config) . ";\n";
    }

    /**
     * Atomically write a PHP-array config file.
     *
     * The optional `$label` is interpolated into error messages so callers
     * can surface domain-specific wording ("Cannot write error pages to ...").
     *
     * @param array<array-key, mixed> $config
     * @throws WriteException on failure to create the parent directory,
     *                        write to it, or atomically swap into place.
     */
    public static function toFile(string $filename, array $config, string $label = 'config'): void
    {
        $contents = self::processConfig($config);

        $dir = dirname($filename);
        if (! is_dir($dir) && ! self::createDirectory($dir) && ! is_dir($dir)) {
            throw new WriteException(sprintf('Cannot create %s directory "%s".', $label, $dir));
        }

        /**
         * Pre-check directory writability so the "Cannot write %s to" wording
         * is preserved for the unwritable-parent case. safe-writer collapses
         * temp-write and rename failures into a single exception type, so the
         * post-write failure path is reported as an install error below.
         */
        if (! is_writable($dir)) {
            throw new WriteException(sprintf('Cannot write %s to "%s".', $label, $filename));
        }

        try {
            FileWriter::writeFile($filename, $contents);
        } catch (SafeWriterException $e) {
            throw new WriteException(sprintf('Cannot install %s at "%s".', $label, $filename), 0, $e);
        }

        self::invalidateOpcache($filename);
    }

    /**
     * Create a directory tree, reporting failure through the return value
     * rather than the warning mkdir() raises, so the caller can decide
     * whether a concurrent writer created it in the meantime.
     */
    private static function createDirectory(string $dir): bool
    {
        set_error_handler(static fn(): bool => true);

        try {
            return mkdir($dir, permissions: 0o755, recursive: true);
        } finally {
            restore_error_handler();
        }
    }

    private static function exportKey(int|string $key): string
    {
        return is_int($key)
            ? (string) $key
            : var_export(
                value: $key,
                return: true,
            );
    }

    /**
     * Drop the file's cached opcode. Best effort: opcache may be absent, or
     * restricted by `opcache.restrict_api`, and neither should fail a write.
     */
    private static function invalidateOpcache(string $filename): void
    {
        if (! function_exists('opcache_invalidate')) {
            return;
        }

        set_error_handler(static fn(): bool => true);

        try {
            opcache_invalidate($filename, force: true);
        } finally {
            restore_error_handler();
        }
    }
}
