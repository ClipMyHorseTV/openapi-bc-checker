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

namespace ClipMyHorse\OpenApi\BcChecker\Service\Comparator;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Schema;
use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;

class SchemaComparator
{
    public function __construct(
        private readonly PathFormatter $pathFormatter
    ) {
    }

    public function detectSchemaBreaks(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        if (
            $old->components === null ||
            $old->components->schemas === null ||
            $new->components === null ||
            $new->components->schemas === null
        ) {
            return $changeSet;
        }

        foreach ($old->components->schemas as $schemaName => $oldSchema) {
            if (!isset($new->components->schemas[$schemaName])) {
                $docPath = $this->pathFormatter->formatPath(
                    $oldSchema,
                    $this->pathFormatter->buildPath('components', 'schemas', (string)$schemaName)
                );
                $changeSet->addMajor(new Change(
                    sprintf('Schema removed: %s (at: %s)', $schemaName, $docPath),
                    Severity::MAJOR,
                    $docPath
                ));
                continue;
            }

            /** @var Schema $newSchema */
            $newSchema = $new->components->schemas[$schemaName];
            /** @var Schema $oldSchemaObj */
            $oldSchemaObj = $oldSchema;

            $propertyBreaks = $this->detectPropertyBreaks($oldSchemaObj, $newSchema, (string)$schemaName);
            $changeSet->merge($propertyBreaks);
        }

        return $changeSet;
    }

    public function detectPropertyBreaks(Schema $old, Schema $new, string $schemaName): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($old->properties === null) {
            return $changeSet;
        }

        foreach ($old->properties as $propName => $oldProp) {
            if ($new->properties === null || !isset($new->properties[$propName])) {
                if (is_array($old->required) && in_array($propName, $old->required, true)) {
                    $docPath = $this->pathFormatter->formatPath(
                        $oldProp,
                        $this->pathFormatter->buildPath('components', 'schemas', $schemaName, 'properties', (string)$propName)
                    );
                    $changeSet->addMajor(new Change(
                        sprintf(
                            'Required property removed from schema: %s.%s (at: %s)',
                            $schemaName,
                            $propName,
                            $docPath
                        ),
                        Severity::MAJOR,
                        $docPath
                    ));
                }
                continue;
            }

            /** @var Schema $oldPropSchema */
            $oldPropSchema = $oldProp;
            /** @var Schema $newPropSchema */
            $newPropSchema = $new->properties[$propName];

            if (
                $oldPropSchema->type !== null &&
                $newPropSchema->type !== null &&
                $oldPropSchema->type !== $newPropSchema->type
            ) {
                $docPath = $this->pathFormatter->formatPath(
                    $newPropSchema,
                    $this->pathFormatter->buildPath('components', 'schemas', $schemaName, 'properties', (string)$propName)
                );
                $changeSet->addMajor(new Change(
                    sprintf(
                        'Property type changed in schema: %s.%s (%s -> %s) (at: %s)',
                        $schemaName,
                        $propName,
                        $oldPropSchema->type,
                        $newPropSchema->type,
                        $docPath
                    ),
                    Severity::MAJOR,
                    $docPath
                ));
            }
        }

        $oldRequired = $old->required ?? [];
        $newRequired = $new->required ?? [];

        foreach ($newRequired as $requiredProp) {
            if (!in_array($requiredProp, $oldRequired, true)) {
                $propPath = $this->pathFormatter->buildPath('components', 'schemas', $schemaName, 'properties', $requiredProp);
                $changeSet->addMajor(new Change(
                    sprintf(
                        'Property became required in schema: %s.%s (at: %s)',
                        $schemaName,
                        $requiredProp,
                        $propPath
                    ),
                    Severity::MAJOR,
                    $propPath
                ));
            }
        }

        return $changeSet;
    }

    public function detectNewSchemas(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($new->components === null || $new->components->schemas === null) {
            return $changeSet;
        }

        foreach ($new->components->schemas as $schemaName => $newSchema) {
            if (
                $old->components === null ||
                $old->components->schemas === null ||
                !isset($old->components->schemas[$schemaName])
            ) {
                $docPath = $this->pathFormatter->formatPath(
                    $newSchema,
                    $this->pathFormatter->buildPath('components', 'schemas', (string)$schemaName)
                );
                $changeSet->addMinor(new Change(
                    sprintf('New schema added: %s (at: %s)', $schemaName, $docPath),
                    Severity::MINOR,
                    $docPath
                ));
            }
        }

        return $changeSet;
    }

    public function detectNewProperties(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        if (
            $old->components === null ||
            $old->components->schemas === null ||
            $new->components === null ||
            $new->components->schemas === null
        ) {
            return $changeSet;
        }

        foreach ($new->components->schemas as $schemaName => $newSchema) {
            if (!isset($old->components->schemas[$schemaName])) {
                continue;
            }

            /** @var Schema $oldSchema */
            $oldSchema = $old->components->schemas[$schemaName];
            /** @var Schema $newSchemaObj */
            $newSchemaObj = $newSchema;

            if ($newSchemaObj->properties === null) {
                continue;
            }

            foreach ($newSchemaObj->properties as $propName => $newProp) {
                if ($oldSchema->properties === null || !isset($oldSchema->properties[$propName])) {
                    $newRequired = $newSchemaObj->required ?? [];
                    if (!in_array($propName, $newRequired, true)) {
                        $docPath = $this->pathFormatter->formatPath(
                            $newProp,
                            $this->pathFormatter->buildPath('components', 'schemas', (string)$schemaName, 'properties', (string)$propName)
                        );
                        $changeSet->addMinor(new Change(
                            sprintf(
                                'New optional property added to schema: %s.%s (at: %s)',
                                $schemaName,
                                $propName,
                                $docPath
                            ),
                            Severity::MINOR,
                            $docPath
                        ));
                    }
                }
            }
        }

        return $changeSet;
    }
}

