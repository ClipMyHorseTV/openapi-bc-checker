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

namespace ClipMyHorse\OpenApi\BcChecker\Service;

use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\PathItem;
use ClipMyHorse\OpenApi\BcChecker\Exception\BcBreakException;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\DocumentationComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\EndpointComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\ParameterComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\PathFormatter;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\PathIterator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\RequestBodyComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\ResponseComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\SchemaComparator;
use ClipMyHorse\OpenApi\BcChecker\Service\Comparator\SpecParser;

class OpenApiComparator
{
    private readonly SpecParser $specParser;
    private readonly PathFormatter $pathFormatter;
    private readonly PathIterator $pathIterator;
    private readonly EndpointComparator $endpointComparator;
    private readonly ParameterComparator $parameterComparator;
    private readonly ResponseComparator $responseComparator;
    private readonly RequestBodyComparator $requestBodyComparator;
    private readonly SchemaComparator $schemaComparator;
    private readonly DocumentationComparator $documentationComparator;

    public function __construct(
        ?SpecParser $specParser = null,
        ?PathFormatter $pathFormatter = null,
        ?PathIterator $pathIterator = null,
        ?EndpointComparator $endpointComparator = null,
        ?ParameterComparator $parameterComparator = null,
        ?ResponseComparator $responseComparator = null,
        ?RequestBodyComparator $requestBodyComparator = null,
        ?SchemaComparator $schemaComparator = null,
        ?DocumentationComparator $documentationComparator = null
    ) {
        $this->specParser = $specParser ?? new SpecParser();
        $this->pathFormatter = $pathFormatter ?? new PathFormatter();
        $this->pathIterator = $pathIterator ?? new PathIterator();
        
        $this->endpointComparator = $endpointComparator ?? new EndpointComparator(
            $this->pathIterator,
            $this->pathFormatter
        );
        
        $this->parameterComparator = $parameterComparator ?? new ParameterComparator(
            $this->pathFormatter
        );
        
        $this->responseComparator = $responseComparator ?? new ResponseComparator(
            $this->pathFormatter
        );
        
        $this->requestBodyComparator = $requestBodyComparator ?? new RequestBodyComparator(
            $this->pathFormatter
        );
        
        $this->schemaComparator = $schemaComparator ?? new SchemaComparator(
            $this->pathFormatter
        );
        
        $this->documentationComparator = $documentationComparator ?? new DocumentationComparator(
            $this->pathIterator,
            $this->pathFormatter
        );
    }

    /**
     * @return array{major: array<string>, minor: array<string>, patch: array<string>}
     * @throws BcBreakException
     */
    public function compare(string $oldSpec, string $newSpec): array
    {
        $oldOpenApi = $this->specParser->parse($oldSpec);
        $newOpenApi = $this->specParser->parse($newSpec);

        $changeSet = new ChangeSet();

        // Detect MAJOR breaking changes
        $changeSet->merge($this->endpointComparator->detectRemovedEndpoints($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->endpointComparator->detectRemovedOperations($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->compareOperationDetails($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->schemaComparator->detectSchemaBreaks($oldOpenApi, $newOpenApi));

        // Detect MINOR additions (backward compatible)
        $changeSet->merge($this->endpointComparator->detectNewEndpoints($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->endpointComparator->detectNewOperations($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->detectNewOperationDetails($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->schemaComparator->detectNewSchemas($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->schemaComparator->detectNewProperties($oldOpenApi, $newOpenApi));

        // Detect PATCH changes (documentation/metadata)
        $changeSet->merge($this->documentationComparator->detectDocumentationChanges($oldOpenApi, $newOpenApi));
        $changeSet->merge($this->documentationComparator->detectExampleChanges($oldOpenApi, $newOpenApi));

        return $changeSet->toArray();
    }

    /**
     * Compare operation details (parameters, responses, request bodies) for breaking changes.
     */
    private function compareOperationDetails(OpenApi $old, OpenApi $new): ChangeSet
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
                    return;
                }

                $changeSet->merge($this->parameterComparator->detectParameterBreaks($oldOperation, $newOperation, $path, $method));
                $changeSet->merge($this->responseComparator->detectRemovedResponses($oldOperation, $newOperation, $path, $method));
                $changeSet->merge($this->requestBodyComparator->detectRequestBodyBreaks($oldOperation, $newOperation, $path, $method));
            });
        });

        return $changeSet;
    }

    /**
     * Detect new operation details (parameters, responses) for minor version changes.
     */
    private function detectNewOperationDetails(OpenApi $old, OpenApi $new): ChangeSet
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
                    return;
                }

                $changeSet->merge($this->parameterComparator->detectNewParameters($oldOperation, $newOperation, $path, $method));
                $changeSet->merge($this->responseComparator->detectNewResponses($oldOperation, $newOperation, $path, $method));
            });
        });

        return $changeSet;
    }
}
