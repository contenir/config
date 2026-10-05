# Upgrading from 0.x to 2.0

2.0 has the same public API as 0.2. Only the platform requirement changes.

| | 0.x | 2.0 |
| --- | --- | --- |
| PHP | ^8.1 | 8.3, 8.4 or 8.5 |

To upgrade, update the constraint:

```bash
composer require contenir/contenir-config:^2.0
```

No code changes are needed. `Reader\PhpArray::fromFile()`,
`Writer\PhpArray::toFile()`, `processConfig()`, `exportArray()` and
`Exception\WriteException` keep their signatures and behaviour.

Projects that must stay on PHP 8.1 or 8.2 can keep using `^0.2`, which is
maintained on the `0.x` branch.

## Package renamed in 2.1

From 2.1, the package is published as `contenir/contenir-config`. It declares
`replace` for `contenir/config`, so the two can never be installed together.
Switch the requirement:

```bash
composer remove contenir/config && composer require contenir/contenir-config:^2.1
```

No code changes are needed: namespaces and classes are unchanged.
