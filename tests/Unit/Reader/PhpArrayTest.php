<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\Unit\Reader;

use Contenir\Config\Reader\PhpArray;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('config')]
final class PhpArrayTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir() . '/contenir-config-reader-' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            foreach (glob($this->tmpDir . '/*') ?: [] as $f) {
                @unlink($f);
            }
            @rmdir($this->tmpDir);
        }
        parent::tearDown();
    }

    private function path(string $name = 'config.php'): string
    {
        return $this->tmpDir . '/' . $name;
    }

    public function testReturnsEmptyWhenFileMissing(): void
    {
        self::assertSame([], PhpArray::fromFile($this->path('not-here.php')));
    }

    public function testReturnsEmptyWhenFileContentsAreNotAnArray(): void
    {
        file_put_contents($this->path(), "<?php\n\nreturn 'not an array';\n");

        self::assertSame([], PhpArray::fromFile($this->path()));
    }

    public function testReturnsEmptyOnParseError(): void
    {
        file_put_contents($this->path(), "<?php\n\nthis is not valid php\n");

        self::assertSame([], PhpArray::fromFile($this->path()));
    }

    public function testReadsPhpArrayConfig(): void
    {
        file_put_contents(
            $this->path(),
            "<?php\n\nreturn ['errors' => ['pages' => [404 => ['title' => 'x']]]];\n",
        );

        $config = PhpArray::fromFile($this->path());

        self::assertSame('x', $config['errors']['pages'][404]['title']);
    }

    public function testReturnsEmptyForUnreadableFile(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        file_put_contents($this->path(), "<?php\nreturn [];\n");
        chmod($this->path(), 0o000);

        try {
            self::assertSame([], PhpArray::fromFile($this->path()));
        } finally {
            chmod($this->path(), 0o644);
        }
    }
}
