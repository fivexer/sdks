<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\ResultContract;
use Fivexer\SDK\Model\TaskContext;
use Fivexer\SDK\Model\WorkerTaskDetail;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;

/**
 * Handing work off and reading it back: the result contract a task is created with, and the
 * outcome read that long-polls until the work is done.
 */
final class AgentDelegationTest extends ClientTestCase
{
    private const DONE = '{"id":"job-1","status":"completed","done":true,"workerId":"w1",'
        . '"result":{"url":"https://example.com"},"terminalAt":1760000000000}';

    public function testATaskCarriesItsResultContractOnTheWire(): void
    {
        $this->enqueueJson(202, '{"id":"job-1","status":"queued"}');

        $this->client()->tasks()->create(
            (new CreateTask(['seo']))->resultContract(
                (new ResultContract())->schema(['type' => 'object', 'required' => ['url']])->acceptanceCriteria('A URL'),
            ),
        );

        $body = \json_decode((string) $this->lastRequest()->getBody(), true);
        self::assertSame(
            ['schema' => ['type' => 'object', 'required' => ['url']], 'acceptanceCriteria' => 'A URL'],
            $body['resultContract'],
        );
    }

    public function testATaskWithoutAContractSendsNone(): void
    {
        $this->enqueueJson(202, '{"id":"job-2","status":"queued"}');

        $this->client()->tasks()->create(new CreateTask(['seo']));

        self::assertArrayNotHasKey('resultContract', \json_decode((string) $this->lastRequest()->getBody(), true));
    }

    public function testTheOutcomeLongPollsWithWaitMs(): void
    {
        $this->enqueueJson(200, self::DONE);

        $outcome = $this->client()->tasks()->outcome('job-1', 20000);

        self::assertSame('/v1/tasks/job-1/outcome', $this->pathOf($this->lastRequest()));
        self::assertSame('waitMs=20000', $this->queryOf($this->lastRequest()));
        self::assertTrue($outcome->done);
        self::assertSame('completed', $outcome->status);
        self::assertSame('w1', $outcome->workerId);
        self::assertSame(['url' => 'https://example.com'], $outcome->result);
        self::assertSame(1760000000000, $outcome->terminalAt);
        self::assertSame('job-1', $outcome->id);
    }

    public function testALiveOutcomeReadsWithoutWaiting(): void
    {
        $this->enqueueJson(200, '{"id":"job/3","status":"queued","done":false,"workerId":null,'
            . '"result":null,"terminalAt":null}');

        $outcome = $this->client()->tasks()->outcome('job/3');

        self::assertSame('/v1/tasks/job%2F3/outcome', $this->pathOf($this->lastRequest()));
        self::assertSame('', $this->queryOf($this->lastRequest()));
        self::assertFalse($outcome->done);
        self::assertNull($outcome->workerId);
        self::assertNull($outcome->result);
        self::assertNull($outcome->terminalAt);
    }

    public function testALongPollOutlastsAShorterClientTimeout(): void
    {
        $this->enqueueJson(200, self::DONE);
        $this->enqueueJson(200, self::DONE);
        $client = $this->clientWithTimeout(5.0);

        $client->tasks()->outcome('job-1', 25000);
        $client->tasks()->outcome('job-1');

        self::assertSame(35.0, $this->history[0]['options']['timeout']);
        // Without a wait the client's own timeout stands.
        self::assertSame(5, (int) $this->history[1]['options']['timeout']);
    }

    public function testAPatientClientKeepsItsOwnTimeout(): void
    {
        $this->enqueueJson(200, self::DONE);
        $this->clientWithTimeout(120.0)->tasks()->outcome('job-1', 25000);
        self::assertSame(120, (int) $this->history[0]['options']['timeout']);
    }

    public function testTheContextAndTheWorkerDetailReadBackTheContract(): void
    {
        $contract = ['schema' => ['type' => 'object'], 'acceptanceCriteria' => 'A URL'];

        $context = TaskContext::fromArray(['taskId' => 't', 'references' => [], 'resultContract' => $contract]);
        self::assertSame(['type' => 'object'], $context->resultContract?->getSchema());
        self::assertSame('A URL', $context->resultContract?->getAcceptanceCriteria());
        self::assertSame($contract, $context->resultContract?->toArray());
        self::assertNull(TaskContext::fromArray(['taskId' => 't', 'references' => []])->resultContract);

        $detail = WorkerTaskDetail::fromArray(['id' => 't', 'status' => 'pending', 'resultContract' => $contract]);
        self::assertSame('A URL', $detail->resultContract?->getAcceptanceCriteria());
    }

    private function clientWithTimeout(float $timeout): Fivexer
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        return new Fivexer(
            'https://api.fivexer.test',
            'sk_test_abc123',
            new Client(['handler' => $stack, 'timeout' => $timeout]),
            0,
        );
    }
}
