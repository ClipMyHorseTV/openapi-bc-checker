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

namespace ClipMyHorse\OpenApi\BcChecker\Command;

use ClipMyHorse\OpenApi\BcChecker\Exception\BcBreakException;
use ClipMyHorse\OpenApi\BcChecker\Service\Git;
use ClipMyHorse\OpenApi\BcChecker\Service\OpenApiComparator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckBcCommand extends Command
{
    private Git $gitService;
    private OpenApiComparator $comparator;

    public function __construct(
        ?Git $gitService = null,
        ?OpenApiComparator $comparator = null
    ) {
        parent::__construct('check:bc');
        $this->gitService = $gitService ?? new Git();
        $this->comparator = $comparator ?? new OpenApiComparator();
    }

    protected function configure(): void
    {
        $this
            ->setDescription('Check OpenAPI specifications for backward compatibility breaking changes')
            ->setHelp('This command compares two OpenAPI specifications and reports BC breaks.')
            ->addArgument(
                'old',
                InputArgument::REQUIRED,
                'Path to old OpenAPI spec file OR old commit ID (when using --git)'
            )
            ->addArgument(
                'new',
                InputArgument::REQUIRED,
                'Path to new OpenAPI spec file OR new commit ID (when using --git)'
            )
            ->addOption(
                'git',
                'g',
                InputOption::VALUE_OPTIONAL,
                'Git repository path (enables git mode)',
                null
            )
            ->addOption(
                'file',
                'f',
                InputOption::VALUE_OPTIONAL,
                'Specific OpenAPI file path in repository (required in git mode)',
                null
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            /** @var string $oldArg */
            $oldArg = $input->getArgument('old');
            /** @var string $newArg */
            $newArg = $input->getArgument('new');
            /** @var string|null $gitPath */
            $gitPath = $input->getOption('git');
            /** @var string|null $filePath */
            $filePath = $input->getOption('file');

            if ($gitPath !== null) {
                return $this->executeGitMode($io, $oldArg, $newArg, $gitPath, $filePath);
            }

            return $this->executeFileMode($io, $oldArg, $newArg);
        } catch (BcBreakException $e) {
            $io->error($e->getMessage());
            return Command::FAILURE;
        }
    }

    /**
     * @throws BcBreakException
     */
    private function executeFileMode(SymfonyStyle $io, string $oldPath, string $newPath): int
    {
        $io->title('OpenAPI BC Break Checker - File Mode');

        if (!file_exists($oldPath)) {
            throw new BcBreakException(sprintf('Old spec file not found: %s', $oldPath));
        }

        if (!file_exists($newPath)) {
            throw new BcBreakException(sprintf('New spec file not found: %s', $newPath));
        }

        $oldContent = file_get_contents($oldPath);
        $newContent = file_get_contents($newPath);

        if ($oldContent === false) {
            throw new BcBreakException(sprintf('Failed to read old spec file: %s', $oldPath));
        }

        if ($newContent === false) {
            throw new BcBreakException(sprintf('Failed to read new spec file: %s', $newPath));
        }

        $io->section('Comparing specifications');
        $io->writeln(sprintf('Old: %s', $oldPath));
        $io->writeln(sprintf('New: %s', $newPath));
        $io->newLine();

        $changes = $this->comparator->compare($oldContent, $newContent);

        return $this->displayResults($io, $changes);
    }

    /**
     * @throws BcBreakException
     */
    private function executeGitMode(
        SymfonyStyle $io,
        string $oldCommit,
        string $newCommit,
        string $gitPath,
        ?string $filePath
    ): int {
        $io->title('OpenAPI BC Break Checker - Git Mode');

        if (!is_dir($gitPath)) {
            throw new BcBreakException(sprintf('Git repository not found: %s', $gitPath));
        }

        if ($filePath === null) {
            throw new BcBreakException(
                'The --file option is required when using git mode. ' .
                'Specify the path to the OpenAPI spec file within the repository.'
            );
        }

        $this->gitService->validateCommit($gitPath, $oldCommit);
        $this->gitService->validateCommit($gitPath, $newCommit);

        $io->section('Comparing specifications from git commits');
        $io->writeln(sprintf('Repository: %s', $gitPath));
        $io->writeln(sprintf('Old commit: %s', $oldCommit));
        $io->writeln(sprintf('New commit: %s', $newCommit));
        $io->writeln(sprintf('File: %s', $filePath));
        $io->newLine();

        $oldContent = $this->gitService->getFileContentFromCommit($gitPath, $oldCommit, $filePath);
        $newContent = $this->gitService->getFileContentFromCommit($gitPath, $newCommit, $filePath);

        $changes = $this->comparator->compare($oldContent, $newContent);

        return $this->displayResults($io, $changes);
    }

    /**
     * @param array{major: array<string>, minor: array<string>, patch: array<string>} $changes
     */
    private function displayResults(SymfonyStyle $io, array $changes): int
    {
        $majorCount = count($changes['major']);
        $minorCount = count($changes['minor']);
        $patchCount = count($changes['patch']);
        $totalCount = $majorCount + $minorCount + $patchCount;

        if ($totalCount === 0) {
            $io->success('No changes detected between the two specifications!');
            return Command::SUCCESS;
        }

        $io->section('Analysis Results');
        $io->writeln(sprintf('Total changes detected: %d', $totalCount));
        $io->newLine();

        // Display MAJOR breaking changes
        if ($majorCount > 0) {
            $io->block(
                sprintf('MAJOR - Breaking Changes (%d)', $majorCount),
                null,
                'fg=white;bg=red',
                ' ',
                true
            );
            $io->listing($changes['major']);
            $io->newLine();
        }

        // Display MINOR additions
        if ($minorCount > 0) {
            $io->block(
                sprintf('MINOR - Backward Compatible Additions (%d)', $minorCount),
                null,
                'fg=black;bg=cyan',
                ' ',
                true
            );
            $io->listing($changes['minor']);
            $io->newLine();
        }

        // Display PATCH changes
        if ($patchCount > 0) {
            $io->block(
                sprintf('PATCH - Documentation/Metadata Changes (%d)', $patchCount),
                null,
                'fg=black;bg=white',
                ' ',
                true
            );
            $io->listing($changes['patch']);
            $io->newLine();
        }

        // Display version bump recommendation
        $this->displayVersionRecommendation($io, $majorCount, $minorCount, $patchCount);

        // Return failure only if MAJOR breaking changes exist
        if ($majorCount > 0) {
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }

    /**
     * Display recommended version bump based on changes
     */
    private function displayVersionRecommendation(
        SymfonyStyle $io,
        int $majorCount,
        int $minorCount,
        int $patchCount
    ): void {
        $io->section('Version Bump Recommendation');

        if ($majorCount > 0) {
            $io->writeln('<fg=red;options=bold>MAJOR version bump required (X.0.0)</>');
            $io->writeln('Breaking changes detected that are incompatible with previous versions.');
            $io->writeln('Example: 1.2.3 → 2.0.0');
        } elseif ($minorCount > 0) {
            $io->writeln('<fg=cyan;options=bold>MINOR version bump recommended (x.Y.0)</>');
            $io->writeln('New backward-compatible functionality added.');
            $io->writeln('Example: 1.2.3 → 1.3.0');
        } elseif ($patchCount > 0) {
            $io->writeln('<fg=white;options=bold>PATCH version bump recommended (x.y.Z)</>');
            $io->writeln('Only documentation or metadata changes detected.');
            $io->writeln('Example: 1.2.3 → 1.2.4');
        }

        $io->newLine();
        $io->writeln('According to Semantic Versioning (https://semver.org/)');
    }
}
