<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\CorrectTimeEntryInput;
use Fivexer\SDK\Model\CreateTimeEntryInput;
use Fivexer\SDK\Model\PublicWorkerIdentity;
use Fivexer\SDK\Model\RoleStretchInput;
use Fivexer\SDK\Model\WorkerBreakStarted;
use Fivexer\SDK\Model\WorkerMe;
use Fivexer\SDK\Model\WorkerOffboardingSummary;
use Fivexer\SDK\Model\WorkerTimeEntry;
use PHPUnit\Framework\TestCase;

/**
 * Fields the TypeScript SDK already reads and these models had not caught up with:
 * time-entry roles, worker photos, break coverage warnings, and the leave settlement
 * on offboarding. Each test pins the wire name and the absent/null distinction.
 */
final class ParityFields202610Test extends TestCase
{
    private const WARNING = ['shiftInstanceId' => 'si_1', 'shiftName' => 'Early', 'remaining' => 1, 'required' => 2, 'kind' => 'headcount'];

    public function testATimeEntryReadsItsRolesAndKeepsNullForAShiftWithout(): void
    {
        $entry = WorkerTimeEntry::fromArray([
            'type' => 'shift',
            'roles' => [
                ['positionId' => 'pos_1', 'startedAt' => '2026-10-01T06:00:00Z', 'endedAt' => '2026-10-01T10:00:00Z', 'label' => 'Forklift'],
                ['positionId' => null, 'startedAt' => '2026-10-01T10:00:00Z', 'endedAt' => null, 'label' => null],
            ],
        ]);
        self::assertNotNull($entry->roles);
        self::assertSame('Forklift', $entry->roles[0]->label);
        self::assertNull($entry->roles[1]->positionId);
        self::assertNull($entry->roles[1]->endedAt);
        self::assertNull(WorkerTimeEntry::fromArray(['type' => 'shift'])->roles);
    }

    public function testTimeEntryWritesSendRolesOnlyWhenGiven(): void
    {
        $roles = [new RoleStretchInput('pos_1', '2026-10-01T06:00:00Z', '2026-10-01T10:00:00Z')];
        $created = (new CreateTimeEntryInput('a', 'b', 'forgot'))->roles($roles)->toArray();
        self::assertSame(
            [['positionId' => 'pos_1', 'startedAt' => '2026-10-01T06:00:00Z', 'endedAt' => '2026-10-01T10:00:00Z']],
            $created['roles'],
        );
        self::assertArrayNotHasKey('roles', (new CreateTimeEntryInput('a', 'b', 'n'))->toArray());
        $corrected = (new CorrectTimeEntryInput('wrong role'))->roles($roles)->toArray();
        self::assertSame('pos_1', $corrected['roles'][0]['positionId']);
        self::assertArrayNotHasKey('roles', (new CorrectTimeEntryInput('n'))->toArray());
    }

    public function testPhotosAndBreakWarningsOnTheWorker(): void
    {
        $me = WorkerMe::fromArray([
            'workerId' => 'w1',
            'photoUrl' => '/v1/worker-photos/abc',
            'photoUploads' => true,
            'breakCoverageWarnings' => [self::WARNING],
        ]);
        self::assertSame('/v1/worker-photos/abc', $me->photoUrl);
        self::assertTrue($me->photoUploads);
        self::assertSame('Early', $me->breakCoverageWarnings[0]->shiftName);
        $bare = WorkerMe::fromArray(['workerId' => 'w1']);
        self::assertNull($bare->photoUrl);
        self::assertFalse($bare->photoUploads);
        self::assertSame([], $bare->breakCoverageWarnings);
        $identity = PublicWorkerIdentity::fromArray(['id' => 'wi_1', 'photoUrl' => '/v1/worker-photos/abc']);
        self::assertSame('/v1/worker-photos/abc', $identity->photoUrl);
    }

    public function testABreakReportsTheShiftsItLeavesShort(): void
    {
        $started = WorkerBreakStarted::fromArray(['workerId' => 'w1', 'since' => 'x', 'onBreak' => true, 'coverageWarnings' => [self::WARNING]]);
        self::assertSame(1, $started->coverageWarnings[0]->remaining);
        self::assertSame('headcount', $started->coverageWarnings[0]->kind);
    }

    public function testOffboardingReadsTheLeaveSettlement(): void
    {
        $summary = WorkerOffboardingSummary::fromArray([
            'workerId' => 'w1',
            'lastDay' => '2026-10-31',
            'leavePayout' => ['paidOutMinutes' => 960, 'recoveredMinutes' => 0],
            'futureLeave' => 2,
            'leaveSettlementError' => null,
        ]);
        self::assertNotNull($summary->leavePayout);
        self::assertSame(960, $summary->leavePayout->paidOutMinutes);
        self::assertSame(2, $summary->futureLeave);
        self::assertNull($summary->leaveSettlementError);
        $older = WorkerOffboardingSummary::fromArray(['workerId' => 'w1', 'lastDay' => '2026-10-31']);
        self::assertNull($older->leavePayout);
        self::assertSame(0, $older->futureLeave);
    }
}
