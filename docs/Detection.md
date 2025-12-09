# BC Break Detection

The tool detects the following types of backward compatibility breaks:

## Endpoints

- Removed Endpoints
- Removed Operations (GET, POST, PUT, DELETE, etc.)

## Parameters

- Removed Required Parameters
- Optional Parameters Becoming Required

## Responses

- Removed Response Status Codes

## Request Bodies

- Removed Required Request Bodies
- Optional Request Bodies Becoming Required

## Schemas

- Removed Schemas
- Removed Required Properties
- Property Type Changes
- Optional Properties Becoming Required

## Example Output

### No BC Breaks

```text
OpenAPI BC Checker - File Mode
===============================

Comparing specifications
------------------------
Old: specs/v1.yaml
New: specs/v2.yaml

[OK] No backward compatibility breaking changes detected!
```

### BC Breaks Detected

```text
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

- Search for the Element in Your OpenAPI File
- Understand the Document Structure
- Navigate Multi-file Specifications
- Use with IDE "Go to" Features (Search for the Path)
