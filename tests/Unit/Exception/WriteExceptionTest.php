<?php

declare(strict_types=1);

namespace Contenir\Config\Tests\Unit\Exception;

use Contenir\Config\Exception\WriteException;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[Group('unit')]
#[Group('config')]
final class WriteExceptionTest extends TestCase
{
    #[Test]
    public function isCatchableAsRuntimeException(): void
    {
        static::assertInstanceOf(RuntimeException::class, new WriteException('failed'));
    }
}
