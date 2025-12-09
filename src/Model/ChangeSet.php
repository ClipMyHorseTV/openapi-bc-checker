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

class ChangeSet
{
    /** @var array<Change> */
    private array $major = [];

    /** @var array<Change> */
    private array $minor = [];

    /** @var array<Change> */
    private array $patch = [];

    public function addMajor(Change $change): void
    {
        $this->major[] = $change;
    }

    public function addMinor(Change $change): void
    {
        $this->minor[] = $change;
    }

    public function addPatch(Change $change): void
    {
        $this->patch[] = $change;
    }

    public function add(Change $change): void
    {
        match ($change->getSeverity()) {
            Severity::MAJOR => $this->addMajor($change),
            Severity::MINOR => $this->addMinor($change),
            Severity::PATCH => $this->addPatch($change),
        };
    }

    public function merge(ChangeSet $other): void
    {
        foreach ($other->major as $change) {
            $this->major[] = $change;
        }
        foreach ($other->minor as $change) {
            $this->minor[] = $change;
        }
        foreach ($other->patch as $change) {
            $this->patch[] = $change;
        }
    }

    /**
     * @return array<Change>
     */
    public function getMajor(): array
    {
        return $this->major;
    }

    /**
     * @return array<Change>
     */
    public function getMinor(): array
    {
        return $this->minor;
    }

    /**
     * @return array<Change>
     */
    public function getPatch(): array
    {
        return $this->patch;
    }

    /**
     * @return array{major: array<string>, minor: array<string>, patch: array<string>}
     */
    public function toArray(): array
    {
        return [
            'major' => array_map(fn(Change $c) => $c->toString(), $this->major),
            'minor' => array_map(fn(Change $c) => $c->toString(), $this->minor),
            'patch' => array_map(fn(Change $c) => $c->toString(), $this->patch),
        ];
    }

    public function isEmpty(): bool
    {
        return empty($this->major) && empty($this->minor) && empty($this->patch);
    }

    public function hasMajor(): bool
    {
        return !empty($this->major);
    }

    public function hasMinor(): bool
    {
        return !empty($this->minor);
    }

    public function hasPatch(): bool
    {
        return !empty($this->patch);
    }
}

