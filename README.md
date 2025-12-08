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

## Installation

### Option 1: From Source

```bash
composer install
```

### Option 2: Using PHAR (Standalone Executable)

Download the latest PHAR file or build it yourself:

```bash
# Build the PHAR
composer build-phar

# The PHAR file will be created as: openapi-bc-checker.phar
```

The PHAR is a self-contained executable that includes all dependencies. You can move it anywhere and run it directly:

```bash
# Make it executable (if needed)
chmod +x openapi-bc-checker.phar

# Move to system path (optional)
sudo mv openapi-bc-checker.phar /usr/local/bin/openapi-bc-checker

# Use it from anywhere
openapi-bc-checker old-spec.yaml new-spec.yaml
```

## Usage

### File Mode

Compare two OpenAPI specification files:

```bash
./bin/openapi-bc-checker old-spec.yaml new-spec.yaml
```

Both JSON and YAML formats are supported:

```bash
./bin/openapi-bc-checker old-spec.json new-spec.json
```

### Git Mode

Compare OpenAPI specs between two git commits:

```bash
./bin/openapi-bc-checker abc123 def456 --git=/path/to/repo --file=openapi.yaml
```

Where:
- `abc123` is the old commit ID
- `def456` is the new commit ID
- `--git=/path/to/repo` specifies the git repository path
- `--file=openapi.yaml` specifies the OpenAPI spec file path within the repository

Short option:

```bash
./bin/openapi-bc-checker abc123 def456 -g /path/to/repo -f openapi.yaml
```

## Exit Codes

- `0` - No BC breaking changes detected
- `1` - BC breaking changes detected or error occurred

## Development

### Running Tests

```bash
composer test
```

Or directly with PHPUnit:

```bash
vendor/bin/phpunit
```

### Running PHPStan

```bash
composer phpstan
```

Or directly:

```bash
vendor/bin/phpstan analyse
```

### Running PHP_CodeSniffer

Check coding standards:

```bash
composer phpcs
```

Auto-fix coding standards:

```bash
composer phpcbf
```

### Run All Checks

```bash
composer check
```

This runs phpcs, phpstan, and phpunit in sequence.

### Building the PHAR

To create a standalone PHAR executable:

```bash
composer build-phar
```

This will create `openapi-bc-checker.phar` in the project root. The PHAR includes:
- All source code
- All vendor dependencies
- Copyright and license information
- Compressed with GZ compression

The PHAR is self-contained and can be distributed as a single file without requiring Composer or vendor dependencies.

## BC Break Detection

The tool detects the following types of backward compatibility breaks:

### Endpoints
- Removed endpoints
- Removed operations (GET, POST, PUT, DELETE, etc.)

### Parameters
- Removed required parameters
- Optional parameters becoming required

### Responses
- Removed response status codes

### Request Bodies
- Removed required request bodies
- Optional request bodies becoming required

### Schemas
- Removed schemas
- Removed required properties
- Property type changes
- Optional properties becoming required

## Example Output

### No BC Breaks

```
OpenAPI BC Checker - File Mode
===============================

Comparing specifications
------------------------
Old: specs/v1.yaml
New: specs/v2.yaml

[OK] No backward compatibility breaking changes detected!
```

### BC Breaks Detected

```
OpenAPI BC Checker - File Mode
===============================

Comparing specifications
------------------------
Old: specs/v1.yaml
New: specs/v2.yaml

[ERROR] Found 4 backward compatibility breaking change(s):

  * Parameter became required: GET /users -> limit (query) (at: paths./users.get.parameters)
  * Endpoint removed: /products (at: paths./products)
  * Property type changed in schema: User.id (string -> integer) (at: components.schemas.User.properties.id)
  * Property became required in schema: User.email (at: components.schemas.User.properties.email)
```

Each breaking change includes:
- **Description**: What changed and where
- **Path reference**: The exact location in the OpenAPI document structure (in `at: ...` format)

The path reference makes it easy to:
- Search for the element in your OpenAPI file
- Understand the document structure
- Navigate multi-file specifications
- Use with IDE "Go to" features (search for the path)

## License

This project is licensed under the **MIT License**.

Copyright (c) 2025 ClipMyHorse.TV Services & Development GmbH

See the [LICENSE](LICENSE) file for the full license text.
