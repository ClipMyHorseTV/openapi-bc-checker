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
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\ParameterComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\PathFormatter;
use PHPUnit\Framework\TestCase;

class ParameterComparatorTest extends TestCase
{
    private ParameterComparator $comparator;

    protected function setUp(): void
    {
        $pathFormatter = new PathFormatter();
        $this->comparator = new ParameterComparator($pathFormatter);
    }

    public function testDetectsRequiredParameterRemoval(): void
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
      parameters:
        - name: id
          in: query
          required: true
          schema:
            type: string
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

        $oldOperation = $oldSpec->paths['/users']->get;
        $newOperation = $newSpec->paths['/users']->get;
        $this->assertNotNull($oldOperation);
        $this->assertNotNull($newOperation);

        $changeSet = $this->comparator->detectParameterBreaks(
            $oldOperation,
            $newOperation,
            '/users',
            'get'
        );
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Required parameter removed', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsParameterBecameRequired(): void
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
      parameters:
        - name: filter
          in: query
          required: false
          schema:
            type: string
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
      parameters:
        - name: filter
          in: query
          required: true
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML
        );

        $oldOperation = $oldSpec->paths['/users']->get;
        $newOperation = $newSpec->paths['/users']->get;
        $this->assertNotNull($oldOperation);
        $this->assertNotNull($newOperation);

        $changeSet = $this->comparator->detectParameterBreaks(
            $oldOperation,
            $newOperation,
            '/users',
            'get'
        );
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertStringContainsString('Parameter became required', $changeSet->getMajor()[0]->getMessage());
        $this->assertSame(Severity::MAJOR, $changeSet->getMajor()[0]->getSeverity());
    }

    public function testDetectsNewOptionalParameter(): void
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
      parameters:
        - name: filter
          in: query
          required: false
          schema:
            type: string
      responses:
        '200':
          description: Success
YAML
        );

        $oldOperation = $oldSpec->paths['/users']->get;
        $newOperation = $newSpec->paths['/users']->get;
        $this->assertNotNull($oldOperation);
        $this->assertNotNull($newOperation);

        $changeSet = $this->comparator->detectNewParameters(
            $oldOperation,
            $newOperation,
            '/users',
            'get'
        );
        
        $this->assertCount(1, $changeSet->getMinor());
        $this->assertStringContainsString('New optional parameter added', $changeSet->getMinor()[0]->getMessage());
        $this->assertSame(Severity::MINOR, $changeSet->getMinor()[0]->getSeverity());
    }

    public function testAllowsOptionalParameterRemoval(): void
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
      parameters:
        - name: filter
          in: query
          required: false
          schema:
            type: string
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

        $oldOperation = $oldSpec->paths['/users']->get;
        $newOperation = $newSpec->paths['/users']->get;
        $this->assertNotNull($oldOperation);
        $this->assertNotNull($newOperation);

        $changeSet = $this->comparator->detectParameterBreaks(
            $oldOperation,
            $newOperation,
            '/users',
            'get'
        );
        
        $this->assertCount(0, $changeSet->getMajor());
    }
}

