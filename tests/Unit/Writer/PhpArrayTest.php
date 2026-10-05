<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\Unit\Writer;

use Contenir\Config\Writer\PhpArray;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

#[Group('unit')]
#[Group('config')]
final class PhpArrayTest extends TestCase
{
    /**
     * @return array<string, array{mixed, string}>
     */
    public static function scalarProvider(): array
    {
        return [
            'string'             => ['hello', "'hello'"],
            'string with quotes' => ["Tom's", "'Tom\\'s'"],
            'integer'            => [42, '42'],
            'float'              => [1.5, '1.5'],
            'true'               => [true, 'true'],
            'false'              => [false, 'false'],
            'null'               => [null, 'NULL'],
        ];
    }

    #[Test]
    public function exportsAssociativeArraysWithQuotedStringKeys(): void
    {
        static::assertSame("[\n    'x' => 1,\n    'y' => 2,\n]", PhpArray::exportArray(['x' => 1, 'y' => 2]));
    }

    #[Test]
    public function exportsEmptyArrayInline(): void
    {
        static::assertSame('[]', PhpArray::exportArray([]));
    }

    #[Test]
    public function exportsListsWithoutKeys(): void
    {
        static::assertSame("[\n    'a',\n    'b',\n]", PhpArray::exportArray(['a', 'b']));
    }

    #[Test]
    public function exportsNonSequentialIntegerKeysUnquoted(): void
    {
        static::assertSame(
            "[\n    403 => 'Forbidden',\n    404 => 'Not Found',\n]",
            PhpArray::exportArray([403 => 'Forbidden', 404 => 'Not Found']),
        );
    }

    #[Test]
    #[DataProvider('scalarProvider')]
    public function exportsScalarsWithVarExport(mixed $value, string $expected): void
    {
        static::assertSame($expected, PhpArray::exportArray($value));
    }

    #[Test]
    public function exportStartsFromTheGivenIndentLevel(): void
    {
        static::assertSame("[\n        'a',\n    ]", PhpArray::exportArray(['a'], indent: 2));
    }

    #[Test]
    public function indentsNestedArraysOneLevelPerDepth(): void
    {
        $expected = <<<'PHP'
            [
                'errors' => [
                    'pages' => [
                        404 => [
                            'title' => 'x',
                        ],
                    ],
                ],
            ]
            PHP;

        static::assertSame($expected, PhpArray::exportArray(['errors' => ['pages' => [404 => ['title' => 'x']]]]));
    }

    #[Test]
    public function processConfigWrapsTheExportInAReturningPhpFile(): void
    {
        static::assertSame("<?php\n\nreturn [\n    'x' => 1,\n];\n", PhpArray::processConfig(['x' => 1]));
    }
}
