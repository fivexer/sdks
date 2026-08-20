<?php

declare(strict_types=1);

/**
 * Test bootstrap. Beyond autoloading, it pre-builds Xdebug's branch/path coverage graph for
 * the SDK + Guzzle by running a mock round-trip under active coverage — then STOPS coverage
 * with cleanup (default) so the per-test data is cleared while the function-level graph Xdebug
 * built stays cached. Without this, the first timed test pays both PHPUnit's coverage-init floor
 * AND Xdebug's first-graph-build (~120ms) for the SDK, pushing it over the per-test budget.
 *
 * (An earlier version passed `false` to xdebug_stop_code_coverage, which kept the accumulated
 * data and made PHPUnit re-process it — the opposite of the intent.)
 */

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\UpsertWorker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

require __DIR__ . '/../vendor/autoload.php';

(static function (): void {
    if (!\function_exists('xdebug_start_code_coverage')) {
        return; // coverage driver not active
    }
    try {
        \xdebug_start_code_coverage(\XDEBUG_CC_UNUSED | \XDEBUG_CC_DEAD_CODE | \XDEBUG_CC_BRANCH_CHECK);

        // Broad round-trip: client, every resource, every model, transport, retry + error path.
        $mock = new MockHandler([
            new Response(202, [], '{"id":"warm","status":"queued"}'),
            new Response(200, [], '{"id":"agent_1"}'),
            new Response(200, [], '{"workers":["agent_1"],"count":1}'),
            new Response(200, [], '{"workerId":"agent_1","taskIds":["warm"]}'),
            new Response(200, [], '{"decisions":[{"id":"d","taskId":"t","workerId":"w","matchedAt":1,"mode":"m","candidates":[]}]}'),
            new Response(200, [], '{"plan":"pro","tasks":{},"workers":1,"meter":{"period":"p","matchedTasks":0,"includedTasksPerMonth":0}}'),
            new Response(503, [], '{"error":{"code":"internal_error","message":"x"}}'),
            new Response(200, [], '{"tasks":[],"nextCursor":null,"hasMore":false}'),
        ]);
        $client = new Fivexer('https://api.fivexer.test', 'sk_warm', new Client(['handler' => HandlerStack::create($mock)]), 1);
        $client->tasks()->create(new CreateTask(['english']));
        $client->workers()->upsert(new UpsertWorker('agent_1'));
        $client->workers()->list();
        $client->workers()->queue('agent_1');
        $client->decisions()->list();
        $client->stats();
        try {
            $client->tasks()->list();
        } catch (\Throwable) {
        }
    } catch (\Throwable) {
        // Warmup is best-effort.
    } finally {
        // cleanup=1 (default): clear the accumulated per-test data, but the function-level
        // branch graph Xdebug built stays cached for the real test run.
        \xdebug_stop_code_coverage();
    }
})();
