# contenir/config

Framework-agnostic PHP-array config file reader and writer for [Contenir CMS](https://github.com/contenir).

Reads and writes the `<?php return [...];` config files that get merged into a Laminas/Mezzio site's configuration. Designed for the admin-writes / Site-reads pattern used by sibling packages like `contenir/cache`, `contenir/maintenance`, and `contenir/errors`.

The reader is tolerant — a missing, unreadable, or syntactically broken file resolves to an empty array rather than throwing, so first-run consumers can ask for state before any has been written. The writer is atomic — partial writes are never visible to readers, and concurrent writers can't race during the write/rename window.

## Install

```bash
composer require contenir/config
```

Requires PHP 8.1+.

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
