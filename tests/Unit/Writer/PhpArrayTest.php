<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\Unit\Writer;

use Contenir\Config\Exception\WriteException;
use Contenir\Config\Writer\PhpArray;
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
        $this->tmpDir = sys_get_temp_dir() . '/contenir-config-writer-' . uniqid('', true);
        mkdir($this->tmpDir, 0o777, true);
    }

    protected function tearDown(): void
    {
        if (is_dir($this->tmpDir)) {
            $this->purge($this->tmpDir);
        }
        parent::tearDown();
    }

    private function purge(string $dir): void
    {
        foreach (glob($dir . '/*') ?: [] as $item) {
            if (is_dir($item)) {
                $this->purge($item);
                @rmdir($item);
            } else {
                @unlink($item);
            }
        }
        @rmdir($dir);
    }

    private function path(string $name = 'config.php'): string
    {
        return $this->tmpDir . '/' . $name;
    }

    public function testToFileWritesArrayThatRoundTripsThroughInclude(): void
    {
        $config = [
            'errors' => [
                'pages' => [
                    404 => ['title' => 'Lost', 'body' => '<p>x</p>'],
                ],
            ],
        ];

        PhpArray::toFile($this->path(), $config);

        self::assertSame($config, include $this->path());
    }

    public function testToFileLeavesNoTempFileResidue(): void
    {
        PhpArray::toFile($this->path(), ['x' => 1]);

        self::assertFileDoesNotExist($this->path() . '.tmp');
    }

    public function testToFileCreatesParentDirectoryWhenMissing(): void
    {
        $nested = $this->tmpDir . '/nested/inner/config.php';

        PhpArray::toFile($nested, ['x' => 1]);

        self::assertFileExists($nested);
    }

    public function testToFileEmitsShortArraySyntaxAndIndentedOutput(): void
    {
        PhpArray::toFile($this->path(), ['errors' => ['pages' => [404 => ['title' => 'x']]]]);

        $contents = (string) file_get_contents($this->path());

        self::assertStringStartsWith("<?php\n\nreturn [\n", $contents);
        self::assertStringContainsString("    'errors' => [\n", $contents);
        self::assertStringContainsString("        'pages' => [\n", $contents);
        self::assertStringContainsString("            404 => [\n", $contents);
        self::assertStringEndsWith("];\n", $contents);
        self::assertStringNotContainsString('array (', $contents, 'Should use [] short syntax');
    }

    public function testToFileRoundTripsScalars(): void
    {
        $config = [
            'string'      => 'hello',
            'with_quotes' => "Tom's \"book\"",
            'int'         => 42,
            'true'        => true,
            'false'       => false,
            'null'        => null,
        ];

        PhpArray::toFile($this->path(), $config);

        self::assertSame($config, include $this->path());
    }

    public function testToFileRoundTripsListAndAssociativeArrays(): void
    {
        $config = [
            'list'  => ['a', 'b', 'c'],
            'assoc' => ['x' => 1, 'y' => 2],
            'mixed' => [
                'inner-list'  => [['a' => 1], ['a' => 2]],
                'inner-assoc' => ['nested' => ['deep' => 'ok']],
            ],
        ];

        PhpArray::toFile($this->path(), $config);

        self::assertSame($config, include $this->path());
    }

    public function testToFileRoundTripsEmptyArray(): void
    {
        PhpArray::toFile($this->path(), ['empty' => []]);

        self::assertSame(['empty' => []], include $this->path());
    }

    public function testToFileRoundTripsIntegerKeys(): void
    {
        PhpArray::toFile($this->path(), [403 => 'Forbidden', 404 => 'Not Found']);

        self::assertSame([403 => 'Forbidden', 404 => 'Not Found'], include $this->path());
    }

    public function testToFileWithLabelInterpolatesIntoCreateDirectoryError(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        $readOnly = $this->tmpDir . '/locked';
        mkdir($readOnly, 0o555, true);

        try {
            $this->expectException(WriteException::class);
            $this->expectExceptionMessageMatches('/Cannot create error pages directory/');
            PhpArray::toFile($readOnly . '/nested/config.php', ['x' => 1], 'error pages');
        } finally {
            chmod($readOnly, 0o755);
            $this->purge($readOnly);
        }
    }

    public function testToFileWithLabelInterpolatesIntoWriteError(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        $readOnly = $this->tmpDir . '/locked';
        mkdir($readOnly, 0o555, true);

        try {
            $this->expectException(WriteException::class);
            $this->expectExceptionMessageMatches('/Cannot write maintenance state to/');
            PhpArray::toFile($readOnly . '/config.php', ['x' => 1], 'maintenance state');
        } finally {
            chmod($readOnly, 0o755);
            rmdir($readOnly);
        }
    }

    public function testToFileWithLabelInterpolatesIntoInstallError(): void
    {
        // Rename can't overwrite a directory with a file — exercises the
        // post-write atomic-swap failure branch.
        $dest = $this->path();
        mkdir($dest, 0o755, true);

        try {
            $this->expectException(WriteException::class);
            $this->expectExceptionMessageMatches('/Cannot install cache control at/');
            PhpArray::toFile($dest, ['x' => 1], 'cache control');
        } finally {
            self::assertFileDoesNotExist($dest . '.tmp');
        }
    }

    public function testToFileDefaultLabelUsesGenericWording(): void
    {
        if (\function_exists('posix_geteuid') && posix_geteuid() === 0) {
            self::markTestSkipped('Running as root bypasses filesystem permission checks.');
        }

        $readOnly = $this->tmpDir . '/locked';
        mkdir($readOnly, 0o555, true);

        try {
            $this->expectException(WriteException::class);
            $this->expectExceptionMessageMatches('/Cannot write config to/');
            PhpArray::toFile($readOnly . '/config.php', ['x' => 1]);
        } finally {
            chmod($readOnly, 0o755);
            rmdir($readOnly);
        }
    }

    public function testProcessConfigReturnsCompleteFileContents(): void
    {
        $output = PhpArray::processConfig(['x' => 1]);

        self::assertStringStartsWith("<?php\n\nreturn ", $output);
        self::assertStringEndsWith(";\n", $output);
        self::assertStringContainsString("'x' => 1", $output);
    }

    public function testExportArrayHandlesScalarTopLevelInput(): void
    {
        // Direct call with a non-array — degenerate but valid; should fall
        // through to var_export of the scalar.
        self::assertSame("'hello'", PhpArray::exportArray('hello'));
        self::assertSame('42', PhpArray::exportArray(42));
        self::assertSame('true', PhpArray::exportArray(true));
        self::assertSame('NULL', PhpArray::exportArray(null));
    }

    public function testWriteExceptionExtendsRuntimeException(): void
    {
        // Existing FileRepository tests catch RuntimeException — verify the
        // typed exception still satisfies that contract.
        self::assertTrue(is_subclass_of(WriteException::class, \RuntimeException::class));
    }
}
