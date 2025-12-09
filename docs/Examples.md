# Examples

The `examples/` directory contains sample OpenAPI specifications demonstrating different types of changes according to semantic versioning:

## Major Version Changes (Breaking Changes)

Compare `api-v1.yaml` with `api-v2.yaml` to see major breaking changes:

```bash
./bin/openapi-bc-checker examples/api-v1.yaml examples/api-v2.yaml
```

This will detect:
- Parameter Became Required: `GET /users -> limit (query)`
- Endpoint Removed: `/products`
- Property Type Changed: `User.id` (string → integer)
- Property Became Required: `User.email`

## Minor Version Changes (Backward-Compatible Additions)

Compare `api-v1.yaml` with `api-v1.1.0.yaml` to see minor additions:

```bash
./bin/openapi-bc-checker examples/api-v1.yaml examples/api-v1.1.0.yaml
```

This will show no breaking changes, but the tool detects these additions:
- New Optional Parameter: `offset` on `GET /users`
- New Response Code: `404` on `GET /users`
- New Operation: `POST /products`
- New Endpoint: `/orders`
- New Optional Property: `phone` on `User` schema
- New Schema: `Order`

## Patch Version Changes (Documentation Updates)

Compare `api-v1.yaml` with `api-v1.0.1.yaml` to see documentation changes:

```bash
./bin/openapi-bc-checker examples/api-v1.yaml examples/api-v1.0.1.yaml
```

This will show no breaking changes, as only documentation was updated:
- Updated Endpoint Descriptions and Summaries
- Added Parameter Descriptions
- Added Schema Property Descriptions
- Added Example Values

