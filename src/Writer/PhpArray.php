<?php

declare(strict_types=1);

namespace Contenir\Config\Writer;

use Contenir\Config\Exception\WriteException;
use Webimpress\SafeWriter\Exception\ExceptionInterface as SafeWriterException;
use Webimpress\SafeWriter\FileWriter;

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
    private const INDENT = '    ';

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

        $dir = \dirname($filename);
        if (! is_dir($dir) && ! @mkdir($dir, 0o755, true) && ! is_dir($dir)) {
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
            throw new WriteException(
                sprintf('Cannot install %s at "%s".', $label, $filename),
                0,
                $e
            );
        }

        if (\function_exists('opcache_invalidate')) {
            @opcache_invalidate($filename, true);
        }
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
     * Recursively render any PHP value as source using short-array syntax.
     *
     * Round-trips scalars, null, and arbitrarily-nested arrays — necessary
     * because consumers may preserve operator-authored data in keys they
     * don't manage.
     */
    public static function exportArray(mixed $value, int $indent = 1): string
    {
        if (! is_array($value)) {
            return var_export($value, true);
        }

        if ($value === []) {
            return '[]';
        }

        $isList   = array_is_list($value);
        $pad      = str_repeat(self::INDENT, $indent);
        $closePad = str_repeat(self::INDENT, $indent - 1);

        $lines = ['['];
        foreach ($value as $key => $child) {
            $exportedChild = self::exportArray($child, $indent + 1);
            if ($isList) {
                $lines[] = sprintf('%s%s,', $pad, $exportedChild);
            } else {
                $keyPart = is_int($key) ? (string) $key : var_export($key, true);
                $lines[] = sprintf('%s%s => %s,', $pad, $keyPart, $exportedChild);
            }
        }
        $lines[] = $closePad . ']';

        return implode("\n", $lines);
    }
}
