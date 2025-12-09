<?php

/**
 * This file is part of the OpenAPI BC Checker.
 *
 * Copyright (c) 2025 ClipMyHorse.TV Services & Development GmbH
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 *
 * @license MIT
 */

declare(strict_types=1);

namespace ClipMyHorse\OpenApi\BcChecker\Tests\Service;

use ClipMyHorse\OpenApi\BcChecker\Exception\BcBreakException;
use ClipMyHorse\OpenApi\BcChecker\Service\OpenApiComparator;
use PHPUnit\Framework\TestCase;

class OpenApiComparatorTest extends TestCase
{
    private OpenApiComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new OpenApiComparator();
    }

    public function testNoBcBreaksWhenSpecsAreIdentical(): void
    {
        $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($spec, $spec);

        $this->assertEmpty($changes['major']);
        $this->assertEmpty($changes['minor']);
        $this->assertEmpty($changes['patch']);
    }

    public function testDetectsRemovedEndpoint(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
  /products:
    get:
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString('Endpoint removed: /products', $changes['major'][0]);
    }

    public function testDetectsRemovedOperation(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
    post:
      responses:
        '201':
          description: Created
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString('Operation removed: POST /users', $changes['major'][0]);
    }

    public function testDetectsRequiredParameterRemoved(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: id
          in: query
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString('Required parameter removed: GET /users -> id (query)', $changes['major'][0]);
    }

    public function testDetectsParameterBecameRequired(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: filter
          in: query
          required: false
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: filter
          in: query
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString('Parameter became required: GET /users -> filter (query)', $changes['major'][0]);
    }

    public function testDetectsSchemaPropertyTypeChanged(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: string
        name:
          type: string
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: integer
        name:
          type: string
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString('Property type changed in schema: User.id (string -> integer)', $changes['major'][0]);
    }

    public function testDetectsSchemaPropertyBecameRequired(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: string
        email:
          type: string
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      required:
        - email
      properties:
        id:
          type: string
        email:
          type: string
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString('Property became required in schema: User.email', $changes['major'][0]);
    }

    public function testThrowsExceptionOnInvalidSpec(): void
    {
        $this->expectException(BcBreakException::class);
        $this->expectExceptionMessage('Failed to parse OpenAPI spec');

        $this->comparator->compare('invalid yaml content [[[', 'another invalid');
    }

    public function testDetectsNewEndpoint(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
  /products:
    get:
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertCount(1, $changes['minor']);
        $this->assertStringContainsString('New endpoint added: /products', $changes['minor'][0]);
    }

    public function testDetectsNewOptionalParameter(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: filter
          in: query
          required: false
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertCount(1, $changes['minor']);
        $this->assertStringContainsString('New optional parameter added: GET /users -> filter (query)', $changes['minor'][0]);
    }

    public function testDetectsNewResponseCode(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
        '404':
          description: Not Found
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertCount(1, $changes['minor']);
        $this->assertStringContainsString('New response code added: GET /users -> 404', $changes['minor'][0]);
    }

    public function testDetectsNewSchema(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: string
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertCount(1, $changes['minor']);
        $this->assertStringContainsString('New schema added: User', $changes['minor'][0]);
    }

    public function testDetectsNewOptionalSchemaProperty(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: string
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths: {}
components:
  schemas:
    User:
      type: object
      properties:
        id:
          type: string
        email:
          type: string
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertCount(1, $changes['minor']);
        $this->assertStringContainsString('New optional property added to schema: User.email', $changes['minor'][0]);
    }

    public function testDetectsDescriptionChange(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      description: Get users
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      description: Retrieve all users
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertEmpty($changes['minor']);
        $this->assertCount(1, $changes['patch']);
        $this->assertStringContainsString('Operation description changed: GET /users', $changes['patch'][0]);
    }

    public function testDetectsSummaryChange(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: Get users
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: Retrieve all users
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertEmpty($changes['minor']);
        $this->assertCount(1, $changes['patch']);
        $this->assertStringContainsString('Operation summary changed: GET /users', $changes['patch'][0]);
    }

    public function testAllowsOptionalParameterRemoval(): void
    {
        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      parameters:
        - name: optional
          in: query
          required: false
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
    }
}
