# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project
adheres to [Semantic Versioning](https://semver.org/).

## [2.1.0] - Unreleased

### Changed

- Renamed from `contenir/config` to `contenir/contenir-config`. The package
  declares `replace` for the old name; require `contenir/contenir-config`
  instead. See [UPGRADE-2.0.md](UPGRADE-2.0.md).

### Added

- Infection mutation testing in CI, MSI 100%.

## [2.0.0] - Unreleased

The public API is unchanged. The major version marks the move to PHP 8.3+
and the php-db QA toolchain shared by all Contenir 2.x packages. See
[UPGRADE-2.0.md](UPGRADE-2.0.md).

### Changed

- `LICENSE` names Contenir as the copyright holder, in line with the other
  Contenir packages, and uses the standard MIT wording.
- Requires PHP 8.3, 8.4 or 8.5. PHP 8.1 and 8.2 are no longer supported.
- `Writer\PhpArray::toFile()` no longer uses the `@` operator. Warnings from
  creating the parent directory and from `opcache_invalidate()` (for example
  under `opcache.restrict_api`) are still contained, by an error handler
  scoped to each call.

### Added

- Continuous integration on PHP 8.3, 8.4 and 8.5 against lowest, locked and
  latest dependencies, with coverage reported to Codecov.
- Separate unit (no I/O) and integration (real filesystem) test suites, with
  100% line and branch coverage.

### Removed

- `squizlabs/php_codesniffer` and `phpcs.xml`, replaced by Mago via
  `php-db/phpdb-qa-tools`.

## [0.2.0]

- Atomic writes via `webimpress/safe-writer`.

## [0.1.0]

- Initial release: PHP-array reader and writer adapted from `Laminas\Config`.
