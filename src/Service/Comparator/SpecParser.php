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

use cebe\openapi\Reader;
use cebe\openapi\spec\OpenApi;
use ClipMyHorse\OpenApi\BcChecker\Exception\BcBreakException;

class SpecParser
{
    /**
     * @throws BcBreakException
     */
    public function parse(string $content): OpenApi
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
}

