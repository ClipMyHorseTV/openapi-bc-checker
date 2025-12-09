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

use cebe\openapi\DocumentContextInterface;
use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use cebe\openapi\spec\Operation;
use cebe\openapi\spec\Parameter;
use cebe\openapi\spec\PathItem;
use cebe\openapi\spec\Reference;
use cebe\openapi\spec\RequestBody;
use cebe\openapi\spec\Schema;
use ClipMyHorse\OpenApi\BcChecker\Exception\BcBreakException;

class OpenApiComparator
{
    /**
     * @return array{major: array<string>, minor: array<string>, patch: array<string>}
     * @throws BcBreakException
     */
    public function compare(string $oldSpec, string $newSpec): array
    {
        $oldOpenApi = $this->parseSpec($oldSpec);
        $newOpenApi = $this->parseSpec($newSpec);

        $result = [
            'major' => [],
            'minor' => [],
            'patch' => [],
        ];

        // Detect MAJOR breaking changes
        $result['major'] = array_merge(
            $result['major'],
            $this->compareEndpoints($oldOpenApi, $newOpenApi)
        );
        $result['major'] = array_merge(
            $result['major'],
            $this->compareSchemas($oldOpenApi, $newOpenApi)
        );

        // Detect MINOR additions (backward compatible)
        $result['minor'] = array_merge(
            $result['minor'],
            $this->detectNewEndpoints($oldOpenApi, $newOpenApi)
        );
        $result['minor'] = array_merge(
            $result['minor'],
            $this->detectNewParameters($oldOpenApi, $newOpenApi)
        );
        $result['minor'] = array_merge(
            $result['minor'],
            $this->detectNewResponses($oldOpenApi, $newOpenApi)
        );
        $result['minor'] = array_merge(
            $result['minor'],
            $this->detectNewSchemas($oldOpenApi, $newOpenApi)
        );
        $result['minor'] = array_merge(
            $result['minor'],
            $this->detectNewSchemaProperties($oldOpenApi, $newOpenApi)
        );

        // Detect PATCH changes (documentation/metadata)
        $result['patch'] = array_merge(
            $result['patch'],
            $this->detectDocumentationChanges($oldOpenApi, $newOpenApi)
        );
        $result['patch'] = array_merge(
            $result['patch'],
            $this->detectExampleChanges($oldOpenApi, $newOpenApi)
        );

        return $result;
    }

    /**
     * @throws BcBreakException
     */
    private function parseSpec(string $content): OpenApi
    {
        try {
            if ($this->isJson($content)) {
                return Reader::readFromJson($content);
            }

            return Reader::readFromYaml($content);
        } catch (\Throwable $e) {
            throw new BcBreakException(
                sprintf('Failed to parse OpenAPI spec: %s', $e->getMessage()),
                0,
                $e
            );
        }
    }

    private function isJson(string $content): bool
    {
        json_decode($content);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * @return array<string>
     */
    private function compareEndpoints(OpenApi $old, OpenApi $new): array
    {
        $breaks = [];

        if ($old->paths === null || $new->paths === null) {
            return $breaks;
        }

        foreach ($old->paths as $path => $pathItem) {
            if (!isset($new->paths[$path])) {
                $docPath = $this->formatPath($pathItem, $this->buildPath('paths', $path));
                $breaks[] = sprintf('Endpoint removed: %s (at: %s)', $path, $docPath);
                continue;
            }

            /** @var PathItem $oldPathItem */
            $oldPathItem = $pathItem;
            /** @var PathItem $newPathItem */
            $newPathItem = $new->paths[$path];

            $breaks = array_merge(
                $breaks,
                $this->compareOperations($path, $oldPathItem, $newPathItem)
            );
        }

        return $breaks;
    }

    /**
     * @return array<string>
     */
    private function compareOperations(string $path, PathItem $old, PathItem $new): array
    {
        $breaks = [];
        $methods = ['get', 'post', 'put', 'delete', 'patch', 'options', 'head', 'trace'];

        foreach ($methods as $method) {
            $oldOperation = $old->$method;
            $newOperation = $new->$method;

            if ($oldOperation !== null && $newOperation === null) {
                $docPath = $this->formatPath($oldOperation, $this->buildPath('paths', $path, $method));
                $breaks[] = sprintf('Operation removed: %s %s (at: %s)', strtoupper($method), $path, $docPath);
                continue;
            }

            if ($oldOperation === null || $newOperation === null) {
                continue;
            }

            $breaks = array_merge(
                $breaks,
                $this->compareParameters($path, $method, $oldOperation, $newOperation)
            );

            $breaks = array_merge(
                $breaks,
                $this->compareResponses($path, $method, $oldOperation, $newOperation)
            );

            $breaks = array_merge(
                $breaks,
                $this->compareRequestBody($path, $method, $oldOperation, $newOperation)
            );
        }

        return $breaks;
    }

    /**
     * @return array<string>
     */
    private function compareParameters(string $path, string $method, Operation $old, Operation $new): array
    {
        $breaks = [];

        if ($old->parameters === null) {
            return $breaks;
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
                            $docPath = $this->formatPath(
                                $newParam,
                                $this->buildPath('paths', $path, $method, 'parameters')
                            );
                            $breaks[] = sprintf(
                                'Parameter became required: %s %s -> %s (%s) (at: %s)',
                                strtoupper($method),
                                $path,
                                $oldParam->name,
                                $oldParam->in,
                                $docPath
                            );
                        }

                        break;
                    }
                }
            }

            if (!$found && $oldParam->required === true) {
                $docPath = $this->formatPath(
                    $oldParam,
                    $this->buildPath('paths', $path, $method, 'parameters')
                );
                $breaks[] = sprintf(
                    'Required parameter removed: %s %s -> %s (%s) (at: %s)',
                    strtoupper($method),
                    $path,
                    $oldParam->name,
                    $oldParam->in,
                    $docPath
                );
            }
        }

        return $breaks;
    }

    /**
     * @return array<string>
     */
    private function compareResponses(string $path, string $method, Operation $old, Operation $new): array
    {
        $breaks = [];

        if ($old->responses === null) {
            return $breaks;
        }

        foreach ($old->responses as $statusCode => $oldResponse) {
            if ($new->responses === null || !isset($new->responses[$statusCode])) {
                $docPath = $this->formatPath(
                    $oldResponse,
                    $this->buildPath('paths', $path, $method, 'responses', (string)$statusCode)
                );
                $breaks[] = sprintf(
                    'Response removed: %s %s -> %s (at: %s)',
                    strtoupper($method),
                    $path,
                    $statusCode,
                    $docPath
                );
            }
        }

        return $breaks;
    }

    /**
     * @return array<string>
     */
    private function compareRequestBody(string $path, string $method, Operation $old, Operation $new): array
    {
        $breaks = [];

        if ($old->requestBody === null) {
            return $breaks;
        }

        if ($old->requestBody instanceof Reference) {
            return $breaks;
        }

        assert($old->requestBody instanceof RequestBody);

        if ($new->requestBody === null) {
            if ($old->requestBody->required === true) {
                $docPath = $this->formatPath(
                    $old->requestBody,
                    $this->buildPath('paths', $path, $method, 'requestBody')
                );
                $breaks[] = sprintf(
                    'Required request body removed: %s %s (at: %s)',
                    strtoupper($method),
                    $path,
                    $docPath
                );
            }
            return $breaks;
        }

        if ($new->requestBody instanceof Reference) {
            return $breaks;
        }

        assert($new->requestBody instanceof RequestBody);

        if ($old->requestBody->required === false && $new->requestBody->required === true) {
            $docPath = $this->formatPath(
                $new->requestBody,
                $this->buildPath('paths', $path, $method, 'requestBody')
            );
            $breaks[] = sprintf(
                'Request body became required: %s %s (at: %s)',
                strtoupper($method),
                $path,
                $docPath
            );
        }

        return $breaks;
    }

    /**
     * @return array<string>
     */
    private function compareSchemas(OpenApi $old, OpenApi $new): array
    {
        $breaks = [];

        if (
            $old->components === null ||
            $old->components->schemas === null ||
            $new->components === null ||
            $new->components->schemas === null
        ) {
            return $breaks;
        }

        foreach ($old->components->schemas as $schemaName => $oldSchema) {
            if (!isset($new->components->schemas[$schemaName])) {
                $docPath = $this->formatPath(
                    $oldSchema,
                    $this->buildPath('components', 'schemas', (string)$schemaName)
                );
                $breaks[] = sprintf('Schema removed: %s (at: %s)', $schemaName, $docPath);
                continue;
            }

            /** @var Schema $newSchema */
            $newSchema = $new->components->schemas[$schemaName];
            /** @var Schema $oldSchemaObj */
            $oldSchemaObj = $oldSchema;

            $breaks = array_merge(
                $breaks,
                $this->compareSchemaProperties($schemaName, $oldSchemaObj, $newSchema)
            );
        }

        return $breaks;
    }

    /**
     * @return array<string>
     */
    private function compareSchemaProperties(string $schemaName, Schema $old, Schema $new): array
    {
        $breaks = [];

        if ($old->properties === null) {
            return $breaks;
        }

        foreach ($old->properties as $propName => $oldProp) {
            if ($new->properties === null || !isset($new->properties[$propName])) {
                if (is_array($old->required) && in_array($propName, $old->required, true)) {
                    $docPath = $this->formatPath(
                        $oldProp,
                        $this->buildPath('components', 'schemas', $schemaName, 'properties', (string)$propName)
                    );
                    $breaks[] = sprintf(
                        'Required property removed from schema: %s.%s (at: %s)',
                        $schemaName,
                        $propName,
                        $docPath
                    );
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
                $docPath = $this->formatPath(
                    $newPropSchema,
                    $this->buildPath('components', 'schemas', $schemaName, 'properties', (string)$propName)
                );
                $breaks[] = sprintf(
                    'Property type changed in schema: %s.%s (%s -> %s) (at: %s)',
                    $schemaName,
                    $propName,
                    $oldPropSchema->type,
                    $newPropSchema->type,
                    $docPath
                );
            }
        }

        $oldRequired = $old->required ?? [];
        $newRequired = $new->required ?? [];

        foreach ($newRequired as $requiredProp) {
            if (!in_array($requiredProp, $oldRequired, true)) {
                $propPath = $this->buildPath('components', 'schemas', $schemaName, 'properties', $requiredProp);
                $breaks[] = sprintf(
                    'Property became required in schema: %s.%s (at: %s)',
                    $schemaName,
                    $requiredProp,
                    $propPath
                );
            }
        }

        return $breaks;
    }

    /**
     * Detect new endpoints (MINOR change)
     * @return array<string>
     */
    private function detectNewEndpoints(OpenApi $old, OpenApi $new): array
    {
        $additions = [];

        if ($new->paths === null) {
            return $additions;
        }

        foreach ($new->paths as $path => $pathItem) {
            if ($old->paths === null || !isset($old->paths[$path])) {
                $docPath = $this->formatPath($pathItem, $this->buildPath('paths', $path));
                $additions[] = sprintf('New endpoint added: %s (at: %s)', $path, $docPath);
                continue;
            }

            /** @var PathItem $oldPathItem */
            $oldPathItem = $old->paths[$path];
            /** @var PathItem $newPathItem */
            $newPathItem = $pathItem;

            $methods = ['get', 'post', 'put', 'delete', 'patch', 'options', 'head', 'trace'];
            foreach ($methods as $method) {
                $oldOperation = $oldPathItem->$method;
                $newOperation = $newPathItem->$method;

                if ($oldOperation === null && $newOperation !== null) {
                    $docPath = $this->formatPath($newOperation, $this->buildPath('paths', $path, $method));
                    $additions[] = sprintf('New operation added: %s %s (at: %s)', strtoupper($method), $path, $docPath);
                }
            }
        }

        return $additions;
    }

    /**
     * Detect new optional parameters (MINOR change)
     * @return array<string>
     */
    private function detectNewParameters(OpenApi $old, OpenApi $new): array
    {
        $additions = [];

        if ($old->paths === null || $new->paths === null) {
            return $additions;
        }

        foreach ($new->paths as $path => $newPathItem) {
            if (!isset($old->paths[$path])) {
                continue;
            }

            /** @var PathItem $oldPathItem */
            $oldPathItem = $old->paths[$path];
            /** @var PathItem $newPathItemObj */
            $newPathItemObj = $newPathItem;

            $methods = ['get', 'post', 'put', 'delete', 'patch', 'options', 'head', 'trace'];
            foreach ($methods as $method) {
                $oldOperation = $oldPathItem->$method;
                $newOperation = $newPathItemObj->$method;

                if ($oldOperation === null || $newOperation === null) {
                    continue;
                }

                if ($newOperation->parameters === null) {
                    continue;
                }

                foreach ($newOperation->parameters as $newParam) {
                    if ($newParam instanceof Reference) {
                        continue;
                    }

                    assert($newParam instanceof Parameter);
                    $found = false;

                    if ($oldOperation->parameters !== null) {
                        foreach ($oldOperation->parameters as $oldParam) {
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
                        $docPath = $this->formatPath(
                            $newParam,
                            $this->buildPath('paths', $path, $method, 'parameters')
                        );
                        $additions[] = sprintf(
                            'New optional parameter added: %s %s -> %s (%s) (at: %s)',
                            strtoupper($method),
                            $path,
                            $newParam->name,
                            $newParam->in,
                            $docPath
                        );
                    }
                }
            }
        }

        return $additions;
    }

    /**
     * Detect new response codes (MINOR change)
     * @return array<string>
     */
    private function detectNewResponses(OpenApi $old, OpenApi $new): array
    {
        $additions = [];

        if ($old->paths === null || $new->paths === null) {
            return $additions;
        }

        foreach ($new->paths as $path => $newPathItem) {
            if (!isset($old->paths[$path])) {
                continue;
            }

            /** @var PathItem $oldPathItem */
            $oldPathItem = $old->paths[$path];
            /** @var PathItem $newPathItemObj */
            $newPathItemObj = $newPathItem;

            $methods = ['get', 'post', 'put', 'delete', 'patch', 'options', 'head', 'trace'];
            foreach ($methods as $method) {
                $oldOperation = $oldPathItem->$method;
                $newOperation = $newPathItemObj->$method;

                if ($oldOperation === null || $newOperation === null) {
                    continue;
                }

                if ($newOperation->responses === null) {
                    continue;
                }

                foreach ($newOperation->responses as $statusCode => $newResponse) {
                    if ($oldOperation->responses === null || !isset($oldOperation->responses[$statusCode])) {
                        $docPath = $this->formatPath(
                            $newResponse,
                            $this->buildPath('paths', $path, $method, 'responses', (string)$statusCode)
                        );
                        $additions[] = sprintf(
                            'New response code added: %s %s -> %s (at: %s)',
                            strtoupper($method),
                            $path,
                            $statusCode,
                            $docPath
                        );
                    }
                }
            }
        }

        return $additions;
    }

    /**
     * Detect new schemas (MINOR change)
     * @return array<string>
     */
    private function detectNewSchemas(OpenApi $old, OpenApi $new): array
    {
        $additions = [];

        if ($new->components === null || $new->components->schemas === null) {
            return $additions;
        }

        foreach ($new->components->schemas as $schemaName => $newSchema) {
            if (
                $old->components === null ||
                $old->components->schemas === null ||
                !isset($old->components->schemas[$schemaName])
            ) {
                $docPath = $this->formatPath(
                    $newSchema,
                    $this->buildPath('components', 'schemas', (string)$schemaName)
                );
                $additions[] = sprintf('New schema added: %s (at: %s)', $schemaName, $docPath);
            }
        }

        return $additions;
    }

    /**
     * Detect new optional schema properties (MINOR change)
     * @return array<string>
     */
    private function detectNewSchemaProperties(OpenApi $old, OpenApi $new): array
    {
        $additions = [];

        if (
            $old->components === null ||
            $old->components->schemas === null ||
            $new->components === null ||
            $new->components->schemas === null
        ) {
            return $additions;
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
                        $docPath = $this->formatPath(
                            $newProp,
                            $this->buildPath('components', 'schemas', (string)$schemaName, 'properties', (string)$propName)
                        );
                        $additions[] = sprintf(
                            'New optional property added to schema: %s.%s (at: %s)',
                            $schemaName,
                            $propName,
                            $docPath
                        );
                    }
                }
            }
        }

        return $additions;
    }

    /**
     * Detect documentation changes (PATCH change)
     * @return array<string>
     */
    private function detectDocumentationChanges(OpenApi $old, OpenApi $new): array
    {
        $changes = [];

        // Check info description changes
        if ($old->info !== null && $new->info !== null) {
            if (
                $old->info->description !== $new->info->description &&
                $old->info->description !== null &&
                $new->info->description !== null
            ) {
                $changes[] = 'API description changed';
            }
        }

        // Check operation description/summary changes
        if ($old->paths !== null && $new->paths !== null) {
            foreach ($old->paths as $path => $oldPathItem) {
                if (!isset($new->paths[$path])) {
                    continue;
                }

                /** @var PathItem $oldPathItemObj */
                $oldPathItemObj = $oldPathItem;
                /** @var PathItem $newPathItem */
                $newPathItem = $new->paths[$path];

                $methods = ['get', 'post', 'put', 'delete', 'patch', 'options', 'head', 'trace'];
                foreach ($methods as $method) {
                    $oldOperation = $oldPathItemObj->$method;
                    $newOperation = $newPathItem->$method;

                    if ($oldOperation === null || $newOperation === null) {
                        continue;
                    }

                    if (
                        $oldOperation->description !== $newOperation->description &&
                        $oldOperation->description !== null &&
                        $newOperation->description !== null
                    ) {
                        $docPath = $this->formatPath(
                            $newOperation,
                            $this->buildPath('paths', $path, $method)
                        );
                        $changes[] = sprintf(
                            'Operation description changed: %s %s (at: %s)',
                            strtoupper($method),
                            $path,
                            $docPath
                        );
                    }

                    if (
                        $oldOperation->summary !== $newOperation->summary &&
                        $oldOperation->summary !== null &&
                        $newOperation->summary !== null
                    ) {
                        $docPath = $this->formatPath(
                            $newOperation,
                            $this->buildPath('paths', $path, $method)
                        );
                        $changes[] = sprintf(
                            'Operation summary changed: %s %s (at: %s)',
                            strtoupper($method),
                            $path,
                            $docPath
                        );
                    }
                }
            }
        }

        return $changes;
    }

    /**
     * Detect example changes (PATCH change)
     * @return array<string>
     */
    private function detectExampleChanges(OpenApi $old, OpenApi $new): array
    {
        $changes = [];

        if ($old->paths === null || $new->paths === null) {
            return $changes;
        }

        foreach ($old->paths as $path => $oldPathItem) {
            if (!isset($new->paths[$path])) {
                continue;
            }

            /** @var PathItem $oldPathItemObj */
            $oldPathItemObj = $oldPathItem;
            /** @var PathItem $newPathItem */
            $newPathItem = $new->paths[$path];

            $methods = ['get', 'post', 'put', 'delete', 'patch', 'options', 'head', 'trace'];
            foreach ($methods as $method) {
                $oldOperation = $oldPathItemObj->$method;
                $newOperation = $newPathItem->$method;

                if ($oldOperation === null || $newOperation === null) {
                    continue;
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
                            $docPath = $this->formatPath(
                                $newParam,
                                $this->buildPath('paths', $path, $method, 'parameters')
                            );
                            $changes[] = sprintf(
                                'Parameter example changed: %s %s -> %s (at: %s)',
                                strtoupper($method),
                                $path,
                                $oldParam->name,
                                $docPath
                            );
                        }
                    }
                }
            }
        }

        return $changes;
    }

    /**
     * Format a document path for display in break messages.
     * Converts object with DocumentContextInterface to a readable path string.
     */
    private function formatPath(mixed $element, string $fallback = ''): string
    {
        if ($element instanceof DocumentContextInterface) {
            $position = $element->getDocumentPosition();
            if ($position !== null) {
                $pointer = $position->getPointer();
                // Convert JSON pointer to readable path (e.g., /paths/~1users/get -> paths./users.get)
                $readable = str_replace('/', '.', ltrim($pointer, '/'));
                $readable = str_replace('~1', '/', $readable);
                $readable = str_replace('~0', '~', $readable);
                return $readable !== '' ? $readable : 'root';
            }
        }
        return $fallback;
    }

    /**
     * Build a path string manually for elements without DocumentContext.
     */
    private function buildPath(string ...$parts): string
    {
        return implode('.', array_filter($parts));
    }
}
