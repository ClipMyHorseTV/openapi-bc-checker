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
use cebe\openapi\spec\PathItem;
use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;

class EndpointComparator
{
    public function __construct(
        private readonly PathIterator $pathIterator,
        private readonly PathFormatter $pathFormatter
    ) {
    }

    public function detectRemovedEndpoints(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        $this->pathIterator->iteratePaths($old, function (string $path, PathItem $oldPathItem) use ($new, $changeSet) {
            if ($new->paths === null || !isset($new->paths[$path])) {
                $docPath = $this->pathFormatter->formatPath(
                    $oldPathItem,
                    $this->pathFormatter->buildPath('paths', $path)
                );
                $changeSet->addMajor(new Change(
                    sprintf('Endpoint removed: %s (at: %s)', $path, $docPath),
                    Severity::MAJOR,
                    $docPath
                ));
            }
        });

        return $changeSet;
    }

    public function detectRemovedOperations(OpenApi $old, OpenApi $new): ChangeSet
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

            $this->pathIterator->iterateOperations($oldPathItem, function (string $method, $oldOperation) use ($newPathItem, $path, $changeSet) {
                $newOperation = $newPathItem->$method;
                if ($newOperation === null) {
                    $docPath = $this->pathFormatter->formatPath(
                        $oldOperation,
                        $this->pathFormatter->buildPath('paths', $path, $method)
                    );
                    $changeSet->addMajor(new Change(
                        sprintf('Operation removed: %s %s (at: %s)', strtoupper($method), $path, $docPath),
                        Severity::MAJOR,
                        $docPath
                    ));
                }
            });
        });

        return $changeSet;
    }

    public function detectNewEndpoints(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        $this->pathIterator->iteratePaths($new, function (string $path, PathItem $newPathItem) use ($old, $changeSet) {
            if ($old->paths === null || !isset($old->paths[$path])) {
                $docPath = $this->pathFormatter->formatPath(
                    $newPathItem,
                    $this->pathFormatter->buildPath('paths', $path)
                );
                $changeSet->addMinor(new Change(
                    sprintf('New endpoint added: %s (at: %s)', $path, $docPath),
                    Severity::MINOR,
                    $docPath
                ));
            }
        });

        return $changeSet;
    }

    public function detectNewOperations(OpenApi $old, OpenApi $new): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($old->paths === null || $new->paths === null) {
            return $changeSet;
        }

        $this->pathIterator->iteratePaths($new, function (string $path, PathItem $newPathItem) use ($old, $changeSet) {
            if (!isset($old->paths[$path])) {
                return;
            }

            /** @var PathItem $oldPathItem */
            $oldPathItem = $old->paths[$path];

            $this->pathIterator->iterateOperations($newPathItem, function (string $method, $newOperation) use ($oldPathItem, $path, $changeSet) {
                $oldOperation = $oldPathItem->$method;
                if ($oldOperation === null) {
                    $docPath = $this->pathFormatter->formatPath(
                        $newOperation,
                        $this->pathFormatter->buildPath('paths', $path, $method)
                    );
                    $changeSet->addMinor(new Change(
                        sprintf('New operation added: %s %s (at: %s)', strtoupper($method), $path, $docPath),
                        Severity::MINOR,
                        $docPath
                    ));
                }
            });
        });

        return $changeSet;
    }
}

