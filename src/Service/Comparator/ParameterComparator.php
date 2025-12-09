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
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\Reference;
use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;

class ParameterComparator
{
    public function __construct(
        private readonly PathFormatter $pathFormatter
    ) {
    }

    public function detectParameterBreaks(Operation $old, Operation $new, string $path, string $method): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($old->parameters === null) {
            return $changeSet;
        }

        foreach ($old->parameters as $oldParam) {
            if ($oldParam instanceof Reference) {
                continue;
            }

            assert($oldParam instanceof Parameter);
            $found = false;

            if ($new->parameters !== null) {
                foreach ($new->parameters as $newParam) {
                    if ($newParam instanceof Reference) {
                        continue;
                    }

                    assert($newParam instanceof Parameter);

                    if ($oldParam->name === $newParam->name && $oldParam->in === $newParam->in) {
                        $found = true;

                        if ($oldParam->required === false && $newParam->required === true) {
                            $docPath = $this->pathFormatter->formatPath(
                                $newParam,
                                $this->pathFormatter->buildPath('paths', $path, $method, 'parameters')
                            );
                            $changeSet->addMajor(new Change(
                                sprintf(
                                    'Parameter became required: %s %s -> %s (%s) (at: %s)',
                                    strtoupper($method),
                                    $path,
                                    $oldParam->name,
                                    $oldParam->in,
                                    $docPath
                                ),
                                Severity::MAJOR,
                                $docPath
                            ));
                        }

                        break;
                    }
                }
            }

            if (!$found && $oldParam->required === true) {
                $docPath = $this->pathFormatter->formatPath(
                    $oldParam,
                    $this->pathFormatter->buildPath('paths', $path, $method, 'parameters')
                );
                $changeSet->addMajor(new Change(
                    sprintf(
                        'Required parameter removed: %s %s -> %s (%s) (at: %s)',
                        strtoupper($method),
                        $path,
                        $oldParam->name,
                        $oldParam->in,
                        $docPath
                    ),
                    Severity::MAJOR,
                    $docPath
                ));
            }
        }

        return $changeSet;
    }

    public function detectNewParameters(Operation $old, Operation $new, string $path, string $method): ChangeSet
    {
        $changeSet = new ChangeSet();

        if ($new->parameters === null) {
            return $changeSet;
        }

        foreach ($new->parameters as $newParam) {
            if ($newParam instanceof Reference) {
                continue;
            }

            assert($newParam instanceof Parameter);
            $found = false;

            if ($old->parameters !== null) {
                foreach ($old->parameters as $oldParam) {
                    if ($oldParam instanceof Reference) {
                        continue;
                    }

                    assert($oldParam instanceof Parameter);

                    if ($oldParam->name === $newParam->name && $oldParam->in === $newParam->in) {
                        $found = true;
                        break;
                    }
                }
            }

            if (!$found && $newParam->required !== true) {
                $docPath = $this->pathFormatter->formatPath(
                    $newParam,
                    $this->pathFormatter->buildPath('paths', $path, $method, 'parameters')
                );
                $changeSet->addMinor(new Change(
                    sprintf(
                        'New optional parameter added: %s %s -> %s (%s) (at: %s)',
                        strtoupper($method),
                        $path,
                        $newParam->name,
                        $newParam->in,
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

