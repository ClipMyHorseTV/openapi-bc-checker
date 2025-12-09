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
use cebe\openapi\spec\PathItem;
use ClipMyHorse\OpenApi\BcChecker\Model\HttpMethod;

class PathIterator
{
    /**
     * Iterate over all paths in an OpenAPI spec.
     *
     * @param callable(string, PathItem): void $callback
     */
    public function iteratePaths(OpenApi $spec, callable $callback): void
    {
        if ($spec->paths === null) {
            return;
        }

        foreach ($spec->paths as $path => $pathItem) {
            assert($pathItem instanceof PathItem);
            $callback($path, $pathItem);
        }
    }

    /**
     * Iterate over all operations (methods) in a PathItem.
     *
     * @param callable(string, Operation): void $callback
     */
    public function iterateOperations(PathItem $pathItem, callable $callback): void
    {
        foreach (HttpMethod::all() as $method) {
            $operation = $pathItem->$method;
            if ($operation !== null) {
                $callback($method, $operation);
            }
        }
    }

    /**
     * Iterate over all paths and their operations in an OpenAPI spec.
     *
     * @param callable(string, string, Operation, PathItem): void $callback
     */
    public function iteratePathsAndOperations(OpenApi $spec, callable $callback): void
    {
        $this->iteratePaths($spec, function (string $path, PathItem $pathItem) use ($callback) {
            $this->iterateOperations($pathItem, function (string $method, Operation $operation) use ($path, $pathItem, $callback) {
                $callback($path, $method, $operation, $pathItem);
            });
        });
    }
}

