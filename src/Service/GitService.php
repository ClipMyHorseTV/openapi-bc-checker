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

use ClipMyHorse\OpenApi\BcChecker\Exception\BcBreakException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

class GitService
{
    /**
     * @throws BcBreakException
     */
    public function getFileContentFromCommit(string $repositoryPath, string $commitId, string $filePath): string
    {
        $process = new Process(
            ['git', 'show', sprintf('%s:%s', $commitId, $filePath)],
            $repositoryPath
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw BcBreakException::failedToGetFileFromCommit($filePath, $commitId, $process->getErrorOutput());
        }

        return $process->getOutput();
    }

    /**
     * @return array<string>
     * @throws BcBreakException
     */
    public function findOpenApiFiles(string $repositoryPath, string $commitId): array
    {
        $process = new Process(
            ['git', 'ls-tree', '-r', '--name-only', $commitId],
            $repositoryPath
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw BcBreakException::failedToListFilesInCommit($commitId, $process->getErrorOutput());
        }

        $files = array_filter(
            explode("\n", trim($process->getOutput())),
            static function (string $file): bool {
                return preg_match('/\.(yaml|yml|json)$/i', $file) === 1;
            }
        );

        return array_values($files);
    }

    /**
     * @throws BcBreakException
     */
    public function validateCommit(string $repositoryPath, string $commitId): void
    {
        $process = new Process(
            ['git', 'cat-file', '-t', $commitId],
            $repositoryPath
        );

        $process->run();

        if (!$process->isSuccessful()) {
            throw BcBreakException::invalidCommitId($commitId);
        }
    }
}
