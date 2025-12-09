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

namespace ClipMyHorse\OpenApi\BcChecker\Tests\Command;

use ClipMyHorse\OpenApi\BcChecker\Command\CheckBcCommand;
use ClipMyHorse\OpenApi\BcChecker\Service\GitService;
use ClipMyHorse\OpenApi\BcChecker\Service\OpenApiComparator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Tester\CommandTester;

class CheckBcCommandTest extends TestCase
{
    private string $fixturesDir;

    protected function setUp(): void
    {
        $this->fixturesDir = sys_get_temp_dir() . '/openapi-bc-checker-test-' . uniqid();
        mkdir($this->fixturesDir, 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->fixturesDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir) ?: [], ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    public function testSuccessWhenNoBcBreaks(): void
    {
        $oldFile = $this->fixturesDir . '/old.yaml';
        $newFile = $this->fixturesDir . '/new.yaml';

        $spec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        file_put_contents($oldFile, $spec);
        file_put_contents($newFile, $spec);

        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => $oldFile,
            'new' => $newFile,
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            'No changes detected',
            $commandTester->getDisplay()
        );
    }

    public function testFailureWhenBcBreaksDetected(): void
    {
        $oldFile = $this->fixturesDir . '/old.yaml';
        $newFile = $this->fixturesDir . '/new.yaml';

        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
  /products:
    get:
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        file_put_contents($oldFile, $oldSpec);
        file_put_contents($newFile, $newSpec);

        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => $oldFile,
            'new' => $newFile,
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Endpoint removed: /products', $commandTester->getDisplay());
    }

    public function testErrorWhenOldFileNotFound(): void
    {
        $newFile = $this->fixturesDir . '/new.yaml';
        file_put_contents($newFile, 'openapi: 3.0.0');

        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => '/nonexistent/old.yaml',
            'new' => $newFile,
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('Old spec file not found', $commandTester->getDisplay());
    }

    public function testErrorWhenNewFileNotFound(): void
    {
        $oldFile = $this->fixturesDir . '/old.yaml';
        file_put_contents($oldFile, 'openapi: 3.0.0');

        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => $oldFile,
            'new' => '/nonexistent/new.yaml',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString('New spec file not found', $commandTester->getDisplay());
    }

    public function testErrorWhenGitModeWithoutFileOption(): void
    {
        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => 'abc123',
            'new' => 'def456',
            '--git' => '.',
        ]);

        $this->assertSame(1, $commandTester->getStatusCode());
        $this->assertStringContainsString(
            '--file option is required when using git mode',
            $commandTester->getDisplay()
        );
    }

    public function testSuccessWhenOnlyMinorChanges(): void
    {
        $oldFile = $this->fixturesDir . '/old.yaml';
        $newFile = $this->fixturesDir . '/new.yaml';

        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      responses:
        '200':
          description: Success
  /products:
    get:
      responses:
        '200':
          description: Success
YAML;

        file_put_contents($oldFile, $oldSpec);
        file_put_contents($newFile, $newSpec);

        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => $oldFile,
            'new' => $newFile,
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('MINOR', $commandTester->getDisplay());
        $this->assertStringContainsString('New endpoint added: /products', $commandTester->getDisplay());
    }

    public function testSuccessWhenOnlyPatchChanges(): void
    {
        $oldFile = $this->fixturesDir . '/old.yaml';
        $newFile = $this->fixturesDir . '/new.yaml';

        $oldSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: Get users
      responses:
        '200':
          description: Success
YAML;

        $newSpec = <<<'YAML'
openapi: 3.0.0
info:
  title: Test API
  version: 1.0.0
paths:
  /users:
    get:
      summary: Retrieve all users
      responses:
        '200':
          description: Success
YAML;

        file_put_contents($oldFile, $oldSpec);
        file_put_contents($newFile, $newSpec);

        $application = new Application();
        $application->add(new CheckBcCommand());

        $command = $application->find('check:bc');
        $commandTester = new CommandTester($command);
        $commandTester->execute([
            'old' => $oldFile,
            'new' => $newFile,
        ]);

        $this->assertSame(0, $commandTester->getStatusCode());
        $this->assertStringContainsString('PATCH', $commandTester->getDisplay());
        $this->assertStringContainsString('Operation summary changed: GET /users', $commandTester->getDisplay());
    }
}
