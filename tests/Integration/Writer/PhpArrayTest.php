<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\Integration\Writer;

use Contenir\Config\Exception\WriteException;
use Contenir\Config\Tests\TestAsset\RacingDirectoryStreamWrapper;
use Contenir\Config\Tests\Trait\TemporaryDirectoryTrait;
use Contenir\Config\Writer\PhpArray;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Webimpress\SafeWriter\Exception\ExceptionInterface as SafeWriterException;

use function dirname;
use function error_clear_last;
use function error_get_last;
use function fileperms;
use function glob;
use function mkdir;
use function sprintf;
use function umask;

#[Group('integration')]
#[Group('config')]
final class PhpArrayTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{array<array-key, mixed>}>
     */
    public static function configProvider(): array
    {
        return [
            'nested'            => [['errors' => ['pages' => [404 => ['title' => 'Lost', 'body' => '<p>x</p>']]]]],
            'scalars'           => [[
                'string'      => 'hello',
                'with_quotes' => "Tom's \"book\"",
                'int'         => 42,
                'float'       => 1.5,
                'true'        => true,
                'false'       => false,
                'null'        => null,
            ]],
            'lists and maps'    => [[
                'list'  => ['a', 'b', 'c'],
                'assoc' => ['x' => 1, 'y' => 2],
                'mixed' => ['inner-list' => [['a' => 1], ['a' => 2]], 'inner-assoc' => ['nested' => ['deep' => 'ok']]],
            ]],
            'empty array value' => [['empty' => []]],
            'integer keys'      => [[403 => 'Forbidden', 404 => 'Not Found']],
            'empty config'      => [[]],
        ];
    }

    #[Test]
    public function createsMissingParentDirectories(): void
    {
        $nested = $this->path('nested/inner/config.php');

        PhpArray::toFile($nested, ['x' => 1]);

        static::assertSame(['x' => 1], include $nested);
    }

    #[Test]
    public function createsMissingParentDirectoriesWithOwnerWritableWorldReadablePermissions(): void
    {
        $nested   = $this->path('nested/config.php');
        $previous = umask(0);

        try {
            PhpArray::toFile($nested, ['x' => 1]);
        } finally {
            umask($previous);
        }

        static::assertSame(0o755, fileperms(dirname($nested)) & 0o777);
    }

    #[Test]
    public function doesNotLeakTheMkdirWarningWhenTheParentDirectoryCannotBeCreated(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);
        error_clear_last();

        $thrown = null;
        try {
            PhpArray::toFile($this->path('locked/nested/config.php'), ['x' => 1]);
        } catch (WriteException $e) {
            $thrown = $e;
        }

        static::assertInstanceOf(WriteException::class, $thrown);
        static::assertNull(error_get_last());
    }

    #[Test]
    public function labelsTheErrorWhenTheParentDirectoryCannotBeCreated(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage(
            sprintf('Cannot create error pages directory "%s".', $this->path('locked/nested')),
        );

        PhpArray::toFile($this->path('locked/nested/config.php'), ['x' => 1], 'error pages');
    }

    #[Test]
    public function labelsTheErrorWhenTheParentDirectoryIsNotWritable(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage(
            sprintf('Cannot write maintenance state to "%s".', $this->path('locked/config.php')),
        );

        PhpArray::toFile($this->path('locked/config.php'), ['x' => 1], 'maintenance state');
    }

    #[Test]
    public function leavesNoTemporaryFilesBehind(): void
    {
        PhpArray::toFile($this->path(), ['x' => 1]);

        static::assertSame([$this->path()], glob("{$this->tmpDir}/*"));
    }

    #[Test]
    public function overwritesAnExistingFile(): void
    {
        PhpArray::toFile($this->path(), ['x' => 1]);
        PhpArray::toFile($this->path(), ['x' => 2]);

        static::assertSame(['x' => 2], include $this->path());
    }

    /**
     * Another writer creating the directory between the existence check and
     * mkdir() is not a failure: the write carries on to the writability check.
     */
    #[Test]
    public function toleratesTheParentDirectoryBeingCreatedConcurrently(): void
    {
        RacingDirectoryStreamWrapper::register();
        $filename = RacingDirectoryStreamWrapper::SCHEME . '://shared/config.php';

        try {
            $this->expectException(WriteException::class);
            $this->expectExceptionMessage(sprintf('Cannot write config to "%s".', $filename));

            PhpArray::toFile($filename, ['x' => 1]);
        } finally {
            RacingDirectoryStreamWrapper::unregister();
        }
    }

    #[Test]
    public function usesGenericWordingWithoutALabel(): void
    {
        $this->skipWhenRunningAsRoot();
        mkdir($this->path('locked'), permissions: 0o555);

        $this->expectException(WriteException::class);
        $this->expectExceptionMessage(sprintf('Cannot write config to "%s".', $this->path('locked/config.php')));

        PhpArray::toFile($this->path('locked/config.php'), ['x' => 1]);
    }

    #[Test]
    public function wrapsAFailedAtomicSwapAsAnInstallError(): void
    {
        mkdir($this->path());

        try {
            PhpArray::toFile($this->path(), ['x' => 1], 'cache control');
            static::fail('Expected a WriteException.');
        } catch (WriteException $e) {
            static::assertSame(sprintf('Cannot install cache control at "%s".', $this->path()), $e->getMessage());
            static::assertSame(0, $e->getCode());
            static::assertInstanceOf(SafeWriterException::class, $e->getPrevious());
        }
    }

    /**
     * @param array<array-key, mixed> $config
     */
    #[Test]
    #[DataProvider('configProvider')]
    public function writtenFileRoundTripsThroughInclude(array $config): void
    {
        PhpArray::toFile($this->path(), $config);

        static::assertSame($config, include $this->path());
    }

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpTemporaryDirectory();
    }

    protected function tearDown(): void
    {
        $this->tearDownTemporaryDirectory();
        parent::tearDown();
    }
}
