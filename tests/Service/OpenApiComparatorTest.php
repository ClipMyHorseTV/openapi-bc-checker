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

        $breaks = $this->comparator->compare($spec, $spec);

        $this->assertEmpty($breaks);
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $breaks);
        $this->assertStringContainsString('Endpoint removed: /products', $breaks[0]);
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $breaks);
        $this->assertStringContainsString('Operation removed: POST /users', $breaks[0]);
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $breaks);
        $this->assertStringContainsString('Required parameter removed: GET /users -> id (query)', $breaks[0]);
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $breaks);
        $this->assertStringContainsString('Parameter became required: GET /users -> filter (query)', $breaks[0]);
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $breaks);
        $this->assertStringContainsString('Property type changed in schema: User.id (string -> integer)', $breaks[0]);
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $breaks);
        $this->assertStringContainsString('Property became required in schema: User.email', $breaks[0]);
    }

    public function testThrowsExceptionOnInvalidSpec(): void
    {
        $this->expectException(BcBreakException::class);
        $this->expectExceptionMessage('Failed to parse OpenAPI spec');

        $this->comparator->compare('invalid yaml content [[[', 'another invalid');
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

        $breaks = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($breaks);
    }
}
