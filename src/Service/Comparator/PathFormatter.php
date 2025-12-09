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

use cebe\openapi\DocumentContextInterface;

class PathFormatter
{
    /**
     * Format a document path for display in change messages.
     * Converts object with DocumentContextInterface to a readable path string.
     */
    public function formatPath(mixed $element, string $fallback = ''): string
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
    public function buildPath(string ...$parts): string
    {
        return implode('.', array_filter($parts));
    }
}

