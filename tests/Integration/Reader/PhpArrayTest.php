<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\Integration\Reader;

use Contenir\Config\Reader\PhpArray;
use Contenir\Config\Tests\Trait\TemporaryDirectoryTrait;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

use function chmod;
use function file_put_contents;

#[Group('integration')]
#[Group('config')]
final class PhpArrayTest extends TestCase
{
    use TemporaryDirectoryTrait;

    /**
     * @return array<string, array{string}>
     */
    public static function unusableContentsProvider(): array
    {
        return [
            'returns a string' => ["<?php\n\nreturn 'not an array';\n"],
            'returns nothing'  => ["<?php\n"],
            'parse error'      => ["<?php\n\nthis is not valid php\n"],
            'throws'           => ["<?php\n\nthrow new \\RuntimeException('boom');\n"],
        ];
    }

    #[Test]
    public function readsTheReturnedArray(): void
    {
        file_put_contents(
            $this->path(),
            data: "<?php\n\nreturn ['errors' => ['pages' => [404 => ['title' => 'x']]]];\n",
        );

        static::assertSame(['errors' => ['pages' => [404 => ['title' => 'x']]]], PhpArray::fromFile($this->path()));
    }

    #[Test]
    #[DataProvider('unusableContentsProvider')]
    public function returnsEmptyArrayWhenFileDoesNotReturnAnArray(string $contents): void
    {
        file_put_contents($this->path(), data: $contents);

        static::assertSame([], PhpArray::fromFile($this->path()));
    }

    #[Test]
    public function returnsEmptyArrayWhenFileIsMissing(): void
    {
        static::assertSame([], PhpArray::fromFile($this->path('not-here.php')));
    }

    #[Test]
    public function returnsEmptyArrayWhenFileIsUnreadable(): void
    {
        $this->skipWhenRunningAsRoot();
        file_put_contents($this->path(), data: "<?php\nreturn ['x' => 1];\n");
        chmod($this->path(), permissions: 0o000);

        static::assertSame([], PhpArray::fromFile($this->path()));
    }

    #[Test]
    public function returnsEmptyArrayWhenPathIsADirectory(): void
    {
        static::assertSame([], PhpArray::fromFile($this->tmpDir));
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
