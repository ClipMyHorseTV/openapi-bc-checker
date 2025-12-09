# Installation

## Option 1: From Source

```bash
composer install
```

## Option 2: Using PHAR (Standalone Executable)

Download the Latest PHAR File or Build It Yourself:

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

The PHAR includes:
- All Source Code
- All Vendor Dependencies
- Copyright and License Information
- Compressed with GZ Compression

The PHAR is self-contained and can be distributed as a single file without requiring Composer or vendor dependencies.

