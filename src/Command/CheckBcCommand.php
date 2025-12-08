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
use ClipMyHorse\OpenApi\BcChecker\Service\GitService;
use ClipMyHorse\OpenApi\BcChecker\Service\OpenApiComparator;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class CheckBcCommand extends Command
{
    private GitService $gitService;
    private OpenApiComparator $comparator;

    public function __construct(
        ?GitService $gitService = null,
        ?OpenApiComparator $comparator = null
    ) {
        parent::__construct('check:bc');
        $this->gitService = $gitService ?? new GitService();
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

        $breaks = $this->comparator->compare($oldContent, $newContent);

        return $this->displayResults($io, $breaks);
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

        $breaks = $this->comparator->compare($oldContent, $newContent);

        return $this->displayResults($io, $breaks);
    }

    /**
     * @param array<string> $breaks
     */
    private function displayResults(SymfonyStyle $io, array $breaks): int
    {
        if (count($breaks) === 0) {
            $io->success('No backward compatibility breaking changes detected!');
            return Command::SUCCESS;
        }

        $io->error(sprintf('Found %d backward compatibility breaking change(s):', count($breaks)));
        $io->listing($breaks);

        return Command::FAILURE;
    }
}
