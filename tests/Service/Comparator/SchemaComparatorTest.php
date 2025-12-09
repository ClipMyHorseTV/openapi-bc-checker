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

namespace ClipMyHorse\OpenApi\BcChecker\Tests\Service\Comparator;

use cebe\openapi\Reader;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\PathFormatter;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\SchemaComparator;
use PHPUnit\Framework\TestCase;

class SchemaComparatorTest extends TestCase
{
    private SchemaComparator $comparator;

    protected function setUp(): void
    {
        $pathFormatter = new PathFormatter();
        $this->comparator = new SchemaComparator($pathFormatter);
    }

    public function testDetectsSchemaRemoval(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
    Product:
      type: object
      properties:
        id:
          type: string
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $changeSet = $this->comparator->detectSchemaBreaks($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Schema removed: Product', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsPropertyTypeChange(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $changeSet = $this->comparator->detectSchemaBreaks($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Property type changed in schema: User.id (string -> integer)', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsPropertyBecameRequired(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $changeSet = $this->comparator->detectSchemaBreaks($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Property became required in schema: User.email', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsNewSchema(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
    Product:
      type: object
      properties:
        id:
          type: string
YAML
        );

        $changeSet = $this->comparator->detectNewSchemas($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMinor());
        $this->assertStringContainsString('New schema added: Product', $changeSet->getMinor()[0]->getMessage());
        $this->assertSame(Severity::MINOR, $changeSet->getMinor()[0]->getSeverity());
    }

    public function testDetectsNewOptionalProperty(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $changeSet = $this->comparator->detectNewProperties($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMinor());
        $this->assertStringContainsString('New optional property added to schema: User.email', $changeSet->getMinor()[0]->getMessage());
        $this->assertSame(Severity::MINOR, $changeSet->getMinor()[0]->getSeverity());
    }
}

