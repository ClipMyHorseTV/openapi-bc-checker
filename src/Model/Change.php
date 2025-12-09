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

namespace ClipMyHorse\OpenApi\BcChecker\Model;

class Change
{
    public function __construct(
        private readonly string $message,
        private readonly Severity $severity,
        private readonly string $documentPath
    ) {
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function getSeverity(): Severity
    {
        return $this->severity;
    }

    public function getDocumentPath(): string
    {
        return $this->documentPath;
    }

    public function toString(): string
    {
        return $this->message;
    }
}

