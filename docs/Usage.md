# Usage

## File Mode

Compare two OpenAPI specification files:

```bash
./bin/openapi-bc-checker old-spec.yaml new-spec.yaml
```

Both JSON and YAML formats are supported:

```bash
./bin/openapi-bc-checker old-spec.json new-spec.json
```

## Git Mode

Compare OpenAPI specs between two git commits:

```bash
./bin/openapi-bc-checker abc123 def456 --git=/path/to/repo --file=openapi.yaml
```

Where:

- `abc123` Is the Old Commit ID
- `def456` Is the New Commit ID
- `--git=/path/to/repo` Specifies the Git Repository Path
- `--file=openapi.yaml` Specifies the OpenAPI Spec File Path Within the Repository

Short Option:

```bash
./bin/openapi-bc-checker abc123 def456 -g /path/to/repo -f openapi.yaml
```

## Exit Codes

- `0` - No BC Breaking Changes Detected
- `1` - BC Breaking Changes Detected or Error Occurred

## Using the PHAR

If you've built or downloaded the PHAR file:

```bash
# Make it executable (if needed)
chmod +x openapi-bc-checker.phar

# Move to system path (optional)
sudo mv openapi-bc-checker.phar /usr/local/bin/openapi-bc-checker

# Use it from anywhere
openapi-bc-checker old-spec.yaml new-spec.yaml
```

