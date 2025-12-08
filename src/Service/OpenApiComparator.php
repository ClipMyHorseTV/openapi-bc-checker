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
     * @return array<string>
     * @throws BcBreakException
     */
    public function compare(string $oldSpec, string $newSpec): array
    {
        $oldOpenApi = $this->parseSpec($oldSpec);
        $newOpenApi = $this->parseSpec($newSpec);

        $breaks = [];

        $breaks = array_merge($breaks, $this->compareEndpoints($oldOpenApi, $newOpenApi));
        $breaks = array_merge($breaks, $this->compareSchemas($oldOpenApi, $newOpenApi));

        return $breaks;
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
