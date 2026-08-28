<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\FivexerSupervisor;
use Fivexer\SDK\FivexerWorker;
use Fivexer\SDK\Resource\Breaks;
use Fivexer\SDK\Resource\Decisions;
use Fivexer\SDK\Resource\History;
use Fivexer\SDK\Resource\Identities;
use Fivexer\SDK\Resource\JoinLinks;
use Fivexer\SDK\Resource\Learning;
use Fivexer\SDK\Resource\NotificationChannels;
use Fivexer\SDK\Resource\NotificationSequences;
use Fivexer\SDK\Resource\Runs;
use Fivexer\SDK\Resource\Skills;
use Fivexer\SDK\Resource\TaskAttachments;
use Fivexer\SDK\Resource\TaskRecurring;
use Fivexer\SDK\Resource\TaskComments;
use Fivexer\SDK\Resource\TaskContexts;
use Fivexer\SDK\Resource\Tasks;
use Fivexer\SDK\Resource\Team;
use Fivexer\SDK\Resource\Teams;
use Fivexer\SDK\Resource\WorkerAttachments;
use Fivexer\SDK\Resource\Workers;
use Fivexer\SDK\Resource\Workflows;
use PHPUnit\Framework\TestCase;

/**
 * Every operation in the contract catalogue is reachable from this SDK.
 *
 * contract/operations.yaml is the shared checklist all four SDKs are held to. This walks it and
 * resolves each operation to a real method, so an endpoint added to the platform cannot quietly
 * go missing here — the failure names the exact operation.
 *
 * Parsed with a small regex rather than a YAML library: the operation names are a flat list of
 * `- name: group.method` lines, and this keeps the SDK free of a test-only dependency.
 */
final class ContractParityTest extends TestCase
{
    /** Which class owns each operation prefix. The method name is the last dotted segment. */
    private const OWNERS = [
        'tasks.context' => TaskContexts::class,
        'tasks.comments' => TaskComments::class,
        'tasks.attachments' => TaskAttachments::class,
        'tasks.recurring' => TaskRecurring::class,
        'tasks' => Tasks::class,
        'workers' => Workers::class,
        'skills' => Skills::class,
        'decisions' => Decisions::class,
        'workflows' => Workflows::class,
        'runs' => Runs::class,
        'learning' => Learning::class,
        'notifications.sequences' => NotificationSequences::class,
        'notifications.channels' => NotificationChannels::class,
        'history' => History::class,
        'team' => Team::class,
        'breaks' => Breaks::class,
        'teams' => Teams::class,
        'joinLinks' => JoinLinks::class,
        'identities' => Identities::class,
        'worker.attachments' => WorkerAttachments::class,
        'worker' => FivexerWorker::class,
        'supervisor' => FivexerSupervisor::class,
    ];

    /**
     * Catalogue operations this SDK does not implement yet.
     *
     * Empty, and meant to stay that way: every operation in the shared catalogue resolves to a
     * real method here. It exists so a genuinely deliberate gap can be recorded rather than
     * hidden — an operation added to the catalogue but not to this list still fails the build.
     *
     * @var list<string>
     */
    private const NOT_YET_IMPLEMENTED = [];

    /** @return list<string> */
    private static function operations(): array
    {
        $path = __DIR__ . '/../../contract/operations.yaml';
        // The catalogue lives at the repo root, one level above php/. Running the suite with
        // only php/ mounted would otherwise fail deep inside the parser rather than here.
        self::assertFileExists($path, 'the contract catalogue must be readable; mount the repo root');

        $text = (string) \file_get_contents($path);
        // Only the `groups:` section describes HTTP operations; `helpers:` below it lists the
        // non-HTTP surface (webhook verification, the quota snapshot), which has no method.
        $groups = \explode("\nhelpers:", \explode("\ngroups:", $text, 2)[1], 2)[0];

        // `\r?$`, not `$`: PCRE's multiline `$` matches immediately before a \n, so on a CRLF
        // checkout the \r sits between the name and the anchor and NOTHING matches — the
        // catalogue parses as zero operations and every assertion below passes vacuously.
        // `.gitattributes` normalises to LF, and this makes the parser survive it either way.
        \preg_match_all('/^\s+- name: ([a-zA-Z][\w.]*)\r?$/m', $groups, $matches);
        /** @var list<string> */
        return $matches[1];
    }

    public function testEveryContractOperationResolvesToAMethodOnThisSdk(): void
    {
        $operations = self::operations();
        // A silent regex miss would make the assertion below vacuously pass.
        self::assertGreaterThanOrEqual(80, \count($operations), 'expected the full catalogue');

        $missing = [];
        foreach ($operations as $operation) {
            if (\in_array($operation, self::NOT_YET_IMPLEMENTED, true)) {
                continue;
            }
            $lastDot = \strrpos($operation, '.');
            // A dotless name is a top-level method on the client itself (stats, portal,
            // slaStats), not a group member. The old `(int)` cast turned strrpos()'s `false`
            // into 0, which silently mis-parsed every such name instead of resolving it.
            if ($lastDot === false) {
                if (!\method_exists(Fivexer::class, $operation)) {
                    $missing[] = $operation;
                }
                continue;
            }
            $prefix = \substr($operation, 0, $lastDot);
            $method = \substr($operation, $lastDot + 1);
            $owner = self::OWNERS[$prefix] ?? null;
            if ($owner === null || !\method_exists($owner, $method)) {
                $missing[] = $operation;
            }
        }

        self::assertSame([], $missing, 'operations missing from the PHP SDK');
    }

    public function testEveryResourceGroupIsReachableFromTheClient(): void
    {
        $client = new Fivexer('https://api.fivexer.test', 'sk_test');

        self::assertInstanceOf(TaskContexts::class, $client->tasks()->context());
        self::assertInstanceOf(TaskComments::class, $client->tasks()->comments());
        self::assertInstanceOf(TaskAttachments::class, $client->tasks()->attachments());
        self::assertInstanceOf(Workers::class, $client->workers());
        self::assertInstanceOf(Skills::class, $client->skills());
        self::assertInstanceOf(Decisions::class, $client->decisions());
        self::assertInstanceOf(Workflows::class, $client->workflows());
        self::assertInstanceOf(Runs::class, $client->runs());
        self::assertInstanceOf(Learning::class, $client->learning());
        self::assertInstanceOf(NotificationSequences::class, $client->notifications()->sequences());
        self::assertInstanceOf(NotificationChannels::class, $client->notifications()->channels());
        self::assertInstanceOf(History::class, $client->history());
        self::assertInstanceOf(Team::class, $client->team());
        self::assertInstanceOf(Breaks::class, $client->breaks());
    }
}
