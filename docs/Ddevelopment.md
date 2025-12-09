# Development

## Running Tests

```bash
composer test
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

## Running PHPStan

```bash
composer phpstan
```

Or directly:

```bash
vendor/bin/phpstan analyse
```

## Running PHP_CodeSniffer

Check Coding Standards:

```bash
composer phpcs
```

Auto-fix Coding Standards:

```bash
composer phpcbf
```

## Run All Checks

```bash
composer check
```

This runs phpcs, phpstan, and phpunit in sequence.

## Building the PHAR

To create a standalone PHAR executable:

```bash
composer build-phar
```

This will create `openapi-bc-checker.phar` in the project root. The PHAR includes:
- All Source Code
- All Vendor Dependencies
- Copyright and License Information
- Compressed with GZ Compression

The PHAR is self-contained and can be distributed as a single file without requiring Composer or vendor dependencies.

