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
    private const FIXTURES_PATH = __DIR__ . '/../fixtures/';
    private const OLD_SPEC_SUFFIX = '-old.yaml';
    private const NEW_SPEC_SUFFIX = '-new.yaml';

    private OpenApiComparator $comparator;

    protected function setUp(): void
    {
        $this->comparator = new OpenApiComparator();
    }

    public function testNoBcBreaksWhenSpecsAreIdentical(): void
    {
        $spec = file_get_contents(self::FIXTURES_PATH . 'identical-spec.yaml');
        $this->assertIsString($spec);

        $changes = $this->comparator->compare($spec, $spec);

        $this->assertEmpty($changes['major']);
        $this->assertEmpty($changes['minor']);
        $this->assertEmpty($changes['patch']);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function majorBreakingChangesProvider(): array
    {
        return [
            'removed endpoint' => [
                'removed-endpoint',
                'Endpoint removed: /products',
            ],
            'removed operation' => [
                'removed-operation',
                'Operation removed: POST /users',
            ],
            'required parameter removed' => [
                'required-parameter-removed',
                'Required parameter removed: GET /users -> id (query)',
            ],
            'parameter became required' => [
                'parameter-became-required',
                'Parameter became required: GET /users -> filter (query)',
            ],
            'schema property type changed' => [
                'schema-property-type-changed',
                'Property type changed in schema: User.id (string -> integer)',
            ],
            'schema property became required' => [
                'schema-property-became-required',
                'Property became required in schema: User.email',
            ],
        ];
    }

    /**
     * @dataProvider majorBreakingChangesProvider
     */
    public function testDetectsMajorBreakingChanges(string $fixturePrefix, string $expectedMessage): void
    {
        $oldSpec = file_get_contents(self::FIXTURES_PATH . $fixturePrefix . self::OLD_SPEC_SUFFIX);
        $newSpec = file_get_contents(self::FIXTURES_PATH . $fixturePrefix . self::NEW_SPEC_SUFFIX);
        $this->assertIsString($oldSpec);
        $this->assertIsString($newSpec);

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertCount(1, $changes['major']);
        $this->assertStringContainsString($expectedMessage, $changes['major'][0]);
    }

    public function testThrowsExceptionOnInvalidSpec(): void
    {
        $this->expectException(BcBreakException::class);
        $this->expectExceptionMessage('Failed to parse OpenAPI spec');

        $this->comparator->compare('invalid yaml content [[[', 'another invalid');
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function minorChangesProvider(): array
    {
        return [
            'new endpoint' => [
                'new-endpoint',
                'New endpoint added: /products',
            ],
            'new optional parameter' => [
                'new-optional-parameter',
                'New optional parameter added: GET /users -> filter (query)',
            ],
            'new response code' => [
                'new-response-code',
                'New response code added: GET /users -> 404',
            ],
            'new schema' => [
                'new-schema',
                'New schema added: User',
            ],
            'new optional schema property' => [
                'new-optional-schema-property',
                'New optional property added to schema: User.email',
            ],
        ];
    }

    /**
     * @dataProvider minorChangesProvider
     */
    public function testDetectsMinorChanges(string $fixturePrefix, string $expectedMessage): void
    {
        $oldSpec = file_get_contents(self::FIXTURES_PATH . $fixturePrefix . self::OLD_SPEC_SUFFIX);
        $newSpec = file_get_contents(self::FIXTURES_PATH . $fixturePrefix . self::NEW_SPEC_SUFFIX);
        $this->assertIsString($oldSpec);
        $this->assertIsString($newSpec);

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertCount(1, $changes['minor']);
        $this->assertStringContainsString($expectedMessage, $changes['minor'][0]);
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function patchChangesProvider(): array
    {
        return [
            'description change' => [
                'description-change',
                'Operation description changed: GET /users',
            ],
            'summary change' => [
                'summary-change',
                'Operation summary changed: GET /users',
            ],
        ];
    }

    /**
     * @dataProvider patchChangesProvider
     */
    public function testDetectsPatchChanges(string $fixturePrefix, string $expectedMessage): void
    {
        $oldSpec = file_get_contents(self::FIXTURES_PATH . $fixturePrefix . self::OLD_SPEC_SUFFIX);
        $newSpec = file_get_contents(self::FIXTURES_PATH . $fixturePrefix . self::NEW_SPEC_SUFFIX);
        $this->assertIsString($oldSpec);
        $this->assertIsString($newSpec);

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
        $this->assertEmpty($changes['minor']);
        $this->assertCount(1, $changes['patch']);
        $this->assertStringContainsString($expectedMessage, $changes['patch'][0]);
    }

    public function testAllowsOptionalParameterRemoval(): void
    {
        $oldSpec = file_get_contents(self::FIXTURES_PATH . 'optional-parameter-removal' . self::OLD_SPEC_SUFFIX);
        $newSpec = file_get_contents(self::FIXTURES_PATH . 'optional-parameter-removal' . self::NEW_SPEC_SUFFIX);
        $this->assertIsString($oldSpec);
        $this->assertIsString($newSpec);

        $changes = $this->comparator->compare($oldSpec, $newSpec);

        $this->assertEmpty($changes['major']);
    }
}
