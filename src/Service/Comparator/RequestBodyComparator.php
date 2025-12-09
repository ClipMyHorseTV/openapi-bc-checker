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
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;

class RequestBodyComparator
{
    public function __construct(
        private readonly PathFormatter $pathFormatter
    ) {
    }

    public function detectRequestBodyBreaks(Operation $old, Operation $new, string $path, string $method): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($old->requestBody === null) {
            return $changeSet;
        }

        if ($old->requestBody instanceof Reference) {
            return $changeSet;
        }

        assert($old->requestBody instanceof RequestBody);

        if ($new->requestBody === null) {
            if ($old->requestBody->required === true) {
                $docPath = $this->pathFormatter->formatPath(
                    $old->requestBody,
                    $this->pathFormatter->buildPath('paths', $path, $method, 'requestBody')
                );
                $changeSet->addMajor(new Change(
                    sprintf(
                        'Required request body removed: %s %s (at: %s)',
                        strtoupper($method),
                        $path,
                        $docPath
                    ),
                    Severity::MAJOR,
                    $docPath
                ));
            }
            return $changeSet;
        }

        if ($new->requestBody instanceof Reference) {
            return $changeSet;
        }

        assert($new->requestBody instanceof RequestBody);

        if ($old->requestBody->required === false && $new->requestBody->required === true) {
            $docPath = $this->pathFormatter->formatPath(
                $new->requestBody,
                $this->pathFormatter->buildPath('paths', $path, $method, 'requestBody')
            );
            $changeSet->addMajor(new Change(
                sprintf(
                    'Request body became required: %s %s (at: %s)',
                    strtoupper($method),
                    $path,
                    $docPath
                ),
                Severity::MAJOR,
                $docPath
            ));
        }

        return $changeSet;
    }
}

