# OpenAPI BC Checker

A command-line tool for detecting backward compatibility (BC) breaking changes in OpenAPI specifications.

## Features

- Compare two OpenAPI specification files (JSON or YAML)
- Compare OpenAPI specs between two git commits
- Detect various types of BC breaks:
  - Removed endpoints
  - Removed operations (HTTP methods)
  - Removed or changed required parameters
  - Removed or changed responses
  - Schema changes (property type changes, new required properties)
  - Request body changes
- Detect minor and patch changes (following Semver)

## Installation

```bash
composer install
```

For more installation options including PHAR distribution, see the [Installation Guide](docs/installation.md).

## Quick Start

```bash
./bin/openapi-bc-checker old-spec.yaml new-spec.yaml
```

## Documentation

- [Usage Guide](docs/Usage.md) - File mode, Git mode, and exit codes
- [Examples](docs/Examples.md) - Sample specifications for major, minor, and patch changes
- [BC Break Detection](docs/Detection.md) - Complete list of detected changes and example output
- [Development](docs/Development.md) - Running tests, linters, and building the PHAR

## License

This project is licensed under the **MIT License**.

Copyright (c) 2025 ClipMyHorse.TV Services & Development GmbH

See the [LICENSE](LICENSE) file for the full license text.
