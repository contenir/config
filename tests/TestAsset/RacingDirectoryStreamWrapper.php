<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\TestAsset;

use function stream_wrapper_register;
use function stream_wrapper_unregister;

/**
 * Stream wrapper that simulates losing a mkdir() race: no directory exists
 * until mkdir() is called, mkdir() then fails as if another writer created the
 * directory first, and from then on the directory exists (read-only). Opening
 * files always fails. Unregister it in a finally block so it never outlives
 * the test.
 *
 * @mago-expect lint:method-name Stream wrapper hooks have fixed snake_case names.
 */
final class RacingDirectoryStreamWrapper
{
    public const string SCHEME = 'contenir-config-race';

    /** @var resource|null Set by PHP when the wrapper is used with a stream context. */
    public mixed $context = null;

    private static bool $directoryExists = false;

    public static function register(): void
    {
        self::$directoryExists = false;
        stream_wrapper_register(self::SCHEME, self::class);
    }

    public static function unregister(): void
    {
        stream_wrapper_unregister(self::SCHEME);
        self::$directoryExists = false;
    }

    public function mkdir(string $path, int $mode, int $options): bool
    {
        self::$directoryExists = true;

        return false;
    }

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        return false;
    }

    /**
     * @return array{mode: int}|false
     */
    public function url_stat(string $path, int $flags): array|false
    {
        return self::$directoryExists ? ['mode' => 0o040_555] : false;
    }
}
