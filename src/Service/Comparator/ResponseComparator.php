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

use cebe\openapi\spec\Operation;
use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;

class ResponseComparator
{
    public function __construct(
        private readonly PathFormatter $pathFormatter
    ) {
    }

    public function detectRemovedResponses(Operation $old, Operation $new, string $path, string $method): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($old->responses === null) {
            return $changeSet;
        }

        foreach ($old->responses as $statusCode => $oldResponse) {
            if ($new->responses === null || !isset($new->responses[$statusCode])) {
                $docPath = $this->pathFormatter->formatPath(
                    $oldResponse,
                    $this->pathFormatter->buildPath('paths', $path, $method, 'responses', (string)$statusCode)
                );
                $changeSet->addMajor(new Change(
                    sprintf(
                        'Response removed: %s %s -> %s (at: %s)',
                        strtoupper($method),
                        $path,
                        $statusCode,
                        $docPath
                    ),
                    Severity::MAJOR,
                    $docPath
                ));
            }
        }

        return $changeSet;
    }

    public function detectNewResponses(Operation $old, Operation $new, string $path, string $method): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($new->responses === null) {
            return $changeSet;
        }

        foreach ($new->responses as $statusCode => $newResponse) {
            if ($old->responses === null || !isset($old->responses[$statusCode])) {
                $docPath = $this->pathFormatter->formatPath(
                    $newResponse,
                    $this->pathFormatter->buildPath('paths', $path, $method, 'responses', (string)$statusCode)
                );
                $changeSet->addMinor(new Change(
                    sprintf(
                        'New response code added: %s %s -> %s (at: %s)',
                        strtoupper($method),
                        $path,
                        $statusCode,
                        $docPath
                    ),
                    Severity::MINOR,
                    $docPath
                ));
            }
        }

        return $changeSet;
    }
}

