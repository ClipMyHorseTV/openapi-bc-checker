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

namespace ClipMyHorse\OpenApi\BcChecker\Exception;

use Exception;

class BcBreakException extends Exception
{
    public static function invalidCommitId(string $id): self
    {
        return new self(sprintf('Invalid commit ID: %s', $id));
    }

    public static function failedToListFilesInCommit(string $commitId, string $errorOutput): self
    {
        return new self(sprintf('Failed to list files in commit "%s": %s', $commitId, $errorOutput));
    }

    public static function failedToGetFileFromCommit(string $filePath, string $commitId, string $errorOutput): self
    {
        return new self(sprintf('Failed to get file "%s" from commit "%s": %s', $filePath, $commitId, $errorOutput));
    }
}
