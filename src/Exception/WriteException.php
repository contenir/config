<?php

declare(strict_types=1);

namespace Contenir\Config\Exception;

use RuntimeException;

/**
 * Thrown by Contenir\Config\Writer when the config file cannot be persisted —
 * unwritable directory, exhausted disk, atomic-swap failure, etc.
 *
 * Extends RuntimeException so existing `catch (RuntimeException)` sites in
 * consumers continue to work without code changes.
 */
final class WriteException extends RuntimeException
{
}
