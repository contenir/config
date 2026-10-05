# contenir/config

[![Continuous Integration](https://github.com/contenir/config/actions/workflows/continuous-integration.yml/badge.svg)](https://github.com/contenir/config/actions/workflows/continuous-integration.yml)
[![codecov](https://codecov.io/gh/contenir/config/graph/badge.svg)](https://codecov.io/gh/contenir/config)

Framework-agnostic PHP-array config file reader and writer for [Contenir CMS](https://github.com/contenir).

Reads and writes the `<?php return [...];` config files that get merged into a Laminas/Mezzio site's configuration. Designed for the admin-writes / Site-reads pattern used by sibling packages like `contenir/cache`, `contenir/maintenance`, and `contenir/errors`.

The reader is tolerant — a missing, unreadable, or syntactically broken file resolves to an empty array rather than throwing, so first-run consumers can ask for state before any has been written. The writer is atomic — partial writes are never visible to readers, and concurrent writers can't race during the write/rename window.

## Install

```bash
composer require contenir/config
```

Requires PHP 8.3, 8.4 or 8.5. The 0.x releases, which support PHP 8.1, remain
available from the `0.x` branch and `v0.*` tags; see [UPGRADE-2.0.md](UPGRADE-2.0.md).

## Usage

### Reading

```php
use Contenir\Config\Reader\PhpArray as ConfigReader;

$config = ConfigReader::fromFile('/var/www/shared/pagecache.local.php');
```

`$config` is always an array — empty if the file doesn't exist, is unreadable, or contains a parse error.

### Writing

```php
use Contenir\Config\Writer\PhpArray as ConfigWriter;

ConfigWriter::toFile($path, $config);
```

The optional third argument is a domain label that gets interpolated into error messages — sibling packages pass things like `'cache control'`, `'maintenance state'`, `'error pages'` so failures surface with consumer-meaningful wording. Save errors throw `Contenir\Config\Exception\WriteException`, which extends `\RuntimeException`.

## Development

The QA toolchain is [php-db/phpdb-qa-tools](https://github.com/php-db/phpdb-qa-tools).
[Mago](https://mago.carthage.software/) is a standalone binary, installed
separately (`brew install mago`).

```bash
composer check             # everything below
composer cs-check          # mago format --check && mago lint
composer static-analysis   # mago analyze
composer test              # unit suite: pure rendering, no I/O
composer test-integration  # integration suite: real filesystem in a temp directory
composer test-coverage     # both suites, clover.xml for Codecov
composer mutation-test     # Infection mutation testing over both suites
```

## License

MIT. See [LICENSE](LICENSE).
