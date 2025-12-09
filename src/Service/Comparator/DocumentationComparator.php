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
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;

class DocumentationComparator
{
    public function __construct(
        private readonly PathIterator $pathIterator,
        private readonly PathFormatter $pathFormatter
    ) {
    }

    public function detectDocumentationChanges(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        // Check info description changes
        if ($old->info !== null && $new->info !== null) {
            if (
                $old->info->description !== $new->info->description &&
                $old->info->description !== null &&
                $new->info->description !== null
            ) {
                $changeSet->addPatch(new Change(
                    'API description changed',
                    Severity::PATCH,
                    'info.description'
                ));
            }
        }

        // Check operation description/summary changes
        if ($old->paths !== null && $new->paths !== null) {
            $this->pathIterator->iteratePaths($old, function (string $path, PathItem $oldPathItem) use ($new, $changeSet) {
                if (!isset($new->paths[$path])) {
                    return;
                }

                /** @var PathItem $newPathItem */
                $newPathItem = $new->paths[$path];

                $this->pathIterator->iterateOperations($oldPathItem, function (string $method, Operation $oldOperation) use ($newPathItem, $path, $changeSet) {
                    $newOperation = $newPathItem->$method;
                    if ($newOperation === null) {
                        return;
                    }

                    if (
                        $oldOperation->description !== $newOperation->description &&
                        $oldOperation->description !== null &&
                        $newOperation->description !== null
                    ) {
                        $docPath = $this->pathFormatter->formatPath(
                            $newOperation,
                            $this->pathFormatter->buildPath('paths', $path, $method)
                        );
                        $changeSet->addPatch(new Change(
                            sprintf(
                                'Operation description changed: %s %s (at: %s)',
                                strtoupper($method),
                                $path,
                                $docPath
                            ),
                            Severity::PATCH,
                            $docPath
                        ));
                    }

                    if (
                        $oldOperation->summary !== $newOperation->summary &&
                        $oldOperation->summary !== null &&
                        $newOperation->summary !== null
                    ) {
                        $docPath = $this->pathFormatter->formatPath(
                            $newOperation,
                            $this->pathFormatter->buildPath('paths', $path, $method)
                        );
                        $changeSet->addPatch(new Change(
                            sprintf(
                                'Operation summary changed: %s %s (at: %s)',
                                strtoupper($method),
                                $path,
                                $docPath
                            ),
                            Severity::PATCH,
                            $docPath
                        ));
                    }
                });
            });
        }

        return $changeSet;
    }

    public function detectExampleChanges(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($old->paths === null || $new->paths === null) {
            return $changeSet;
        }

        $this->pathIterator->iteratePaths($old, function (string $path, PathItem $oldPathItem) use ($new, $changeSet) {
            if (!isset($new->paths[$path])) {
                return;
            }

            /** @var PathItem $newPathItem */
            $newPathItem = $new->paths[$path];

            $this->pathIterator->iterateOperations($oldPathItem, function (string $method, Operation $oldOperation) use ($newPathItem, $path, $changeSet) {
                $newOperation = $newPathItem->$method;
                if ($newOperation === null) {
                    return;
                }

                // Check parameter examples
                if ($oldOperation->parameters !== null && $newOperation->parameters !== null) {
                    foreach ($oldOperation->parameters as $idx => $oldParam) {
                        if (
                            $oldParam instanceof Reference ||
                            !isset($newOperation->parameters[$idx]) ||
                            $newOperation->parameters[$idx] instanceof Reference
                        ) {
                            continue;
                        }

                        assert($oldParam instanceof Parameter);
                        assert($newOperation->parameters[$idx] instanceof Parameter);

                        $newParam = $newOperation->parameters[$idx];

                        if (
                            $oldParam->name === $newParam->name &&
                            $oldParam->example !== $newParam->example &&
                            $oldParam->example !== null &&
                            $newParam->example !== null
                        ) {
                            $docPath = $this->pathFormatter->formatPath(
                                $newParam,
                                $this->pathFormatter->buildPath('paths', $path, $method, 'parameters')
                            );
                            $changeSet->addPatch(new Change(
                                sprintf(
                                    'Parameter example changed: %s %s -> %s (at: %s)',
                                    strtoupper($method),
                                    $path,
                                    $oldParam->name,
                                    $docPath
                                ),
                                Severity::PATCH,
                                $docPath
                            ));
                        }
                    }
                }
            });
        });

        return $changeSet;
    }
}

