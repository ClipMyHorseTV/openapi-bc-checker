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
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\EndpointComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\PathFormatter;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\PathIterator;
use PHPUnit\Framework\TestCase;

class EndpointComparatorTest extends TestCase
{
    private EndpointComparator $comparator;

    protected function setUp(): void
    {
        $pathIterator = new PathIterator();
        $pathFormatter = new PathFormatter();
        $this->comparator = new EndpointComparator($pathIterator, $pathFormatter);
    }

    public function testDetectsRemovedEndpoint(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
  /products:
    get:
      summary: Get products
      responses:
        '200':
          description: Success
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $changeSet = $this->comparator->detectRemovedEndpoints($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Endpoint removed: /products', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsRemovedOperation(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
    post:
      summary: Create user
      responses:
        '201':
          description: Created
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $changeSet = $this->comparator->detectRemovedOperations($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Operation removed: POST /users', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsNewEndpoint(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
  /products:
    get:
      summary: Get products
      responses:
        '200':
          description: Success
YAML
        );

        $changeSet = $this->comparator->detectNewEndpoints($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMinor());
        $this->assertStringContainsString('New endpoint added: /products', $changeSet->getMinor()[0]->getMessage());
        $this->assertSame(Severity::MINOR, $changeSet->getMinor()[0]->getSeverity());
    }

    public function testDetectsNewOperation(): void
    {
        $oldSpec = Reader::readFromYaml(<<<YAML
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
YAML
        );

        $newSpec = Reader::readFromYaml(<<<YAML
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
    post:
      summary: Create user
      responses:
        '201':
          description: Created
YAML
        );

        $changeSet = $this->comparator->detectNewOperations($oldSpec, $newSpec);
        
        $this->assertCount(1, $changeSet->getMinor());
        $this->assertStringContainsString('New operation added: POST /users', $changeSet->getMinor()[0]->getMessage());
        $this->assertSame(Severity::MINOR, $changeSet->getMinor()[0]->getSeverity());
    }
}

