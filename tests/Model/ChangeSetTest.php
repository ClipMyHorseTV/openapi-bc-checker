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

namespace ClipMyHorse\OpenApi\BcChecker\Tests\Model;

use ClipMyHorse\OpenApi\BcChecker\Model\Change;
use ClipMyHorse\OpenApi\BcChecker\Model\ChangeSet;
use ClipMyHorse\OpenApi\BcChecker\Model\Severity;
use PHPUnit\Framework\TestCase;

class ChangeSetTest extends TestCase
{
    public function testCanAddChanges(): void
    {
        $changeSet = new ChangeSet();
        
        $majorChange = new Change('Major break', Severity::MAJOR, 'path.to.break');
        $minorChange = new Change('Minor addition', Severity::MINOR, 'path.to.addition');
        $patchChange = new Change('Patch update', Severity::PATCH, 'path.to.patch');
        
        $changeSet->addMajor($majorChange);
        $changeSet->addMinor($minorChange);
        $changeSet->addPatch($patchChange);
        
        $this->assertCount(1, $changeSet->getMajor());
        $this->assertCount(1, $changeSet->getMinor());
        $this->assertCount(1, $changeSet->getPatch());
    }

    public function testCanAddChangeUsingSeverity(): void
    {
        $changeSet = new ChangeSet();
        
        $majorChange = new Change('Major break', Severity::MAJOR, 'path.to.break');
        $changeSet->add($majorChange);
        
        $this->assertCount(1, $changeSet->getMajor());
    }

    public function testCanMergeChangeSets(): void
    {
        $changeSet1 = new ChangeSet();
        $changeSet1->addMajor(new Change('Break 1', Severity::MAJOR, 'path1'));
        
        $changeSet2 = new ChangeSet();
        $changeSet2->addMajor(new Change('Break 2', Severity::MAJOR, 'path2'));
        $changeSet2->addMinor(new Change('Addition', Severity::MINOR, 'path3'));
        
        $changeSet1->merge($changeSet2);
        
        $this->assertCount(2, $changeSet1->getMajor());
        $this->assertCount(1, $changeSet1->getMinor());
    }

    public function testConvertsToArray(): void
    {
        $changeSet = new ChangeSet();
        $changeSet->addMajor(new Change('Major break', Severity::MAJOR, 'path1'));
        $changeSet->addMinor(new Change('Minor addition', Severity::MINOR, 'path2'));
        $changeSet->addPatch(new Change('Patch update', Severity::PATCH, 'path3'));
        
        $array = $changeSet->toArray();
        
        $this->assertIsArray($array);
        $this->assertArrayHasKey('major', $array);
        $this->assertArrayHasKey('minor', $array);
        $this->assertArrayHasKey('patch', $array);
        $this->assertCount(1, $array['major']);
        $this->assertCount(1, $array['minor']);
        $this->assertCount(1, $array['patch']);
        $this->assertSame('Major break', $array['major'][0]);
    }

    public function testEmptyCheck(): void
    {
        $changeSet = new ChangeSet();
        $this->assertTrue($changeSet->isEmpty());
        
        $changeSet->addMinor(new Change('Minor addition', Severity::MINOR, 'path'));
        $this->assertFalse($changeSet->isEmpty());
    }

    public function testHasChecks(): void
    {
        $changeSet = new ChangeSet();
        $this->assertFalse($changeSet->hasMajor());
        $this->assertFalse($changeSet->hasMinor());
        $this->assertFalse($changeSet->hasPatch());
        
        $changeSet->addMajor(new Change('Major break', Severity::MAJOR, 'path'));
        $this->assertTrue($changeSet->hasMajor());
    }
}

