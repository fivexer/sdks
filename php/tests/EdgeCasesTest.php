<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Exception\FivexerException;
use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\QuotaInfo;
use Fivexer\SDK\Model\Task;
use Fivexer\SDK\Model\UpsertWorker;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;

/**
 * Edge cases: transport failures, error-envelope variants, nullable inputs,
 * and model branches (archived reads, quota presence).
 */
final class EdgeCasesTest extends ClientTestCase
{
    public function test_an_unreachable_host_surfaces_as_a_fivexer_exception(): void
    {
        // MockHandler throws a deterministic ConnectException — no real network, no timing risk.
        $this->mock->append(new ConnectException('connection refused', new Request('GET', 'https://api.fivexer.test/v1/stats')));

        $this->expectException(FivexerException::class);
        $this->client()->stats();
    }

    public function test_a_request_exception_without_a_response_surfaces_as_a_fivexer_exception(): void
    {
        // A transport error that produced no HTTP response at all (RequestException carries no response).
        $this->mock->append(new \GuzzleHttp\Exception\RequestException(
            'request failed before any response',
            new Request('GET', 'https://api.fivexer.test/v1/stats'),
        ));

        $this->expectException(FivexerException::class);
        $this->client()->stats();
    }

    public function test_a_200_response_with_an_empty_body_is_treated_as_no_content(): void
    {
        $this->enqueueEmpty(200); // 200 but no body

        // cancel ignores the (null) result; the empty-body branch must not throw.
        $this->client()->tasks()->cancel('task_8fk2');

        self::assertSame(1, \count($this->history));
    }

    public function test_a_200_response_with_a_json_scalar_body_returns_null(): void
    {
        $this->enqueueJson(200, 'null'); // valid JSON, but not an object/array

        $this->client()->tasks()->cancel('task_8fk2');

        self::assertSame(1, \count($this->history));
    }

    public function test_an_error_envelope_without_an_error_key_falls_back_to_unknown(): void
    {
        $this->enqueueJson(500, '{"unrelated":true}');

        try {
            $this->client()->tasks()->list();
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame(500, $e->statusCode);
            self::assertSame('unknown_error', $e->apiCode);
        }
    }

    public function test_an_error_that_is_not_an_object_falls_back_to_unknown(): void
    {
        $this->enqueueJson(409, '{"error":"just a string"}');

        try {
            $this->client()->tasks()->get('t');
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame('unknown_error', $e->apiCode);
        }
    }

    public function test_an_empty_error_object_falls_back_to_unknown_with_default_message(): void
    {
        $this->enqueueJson(409, '{"error":{}}');

        try {
            $this->client()->tasks()->get('t');
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame('unknown_error', $e->apiCode);
            self::assertSame('http 409', $e->getMessage());
        }
    }

    public function test_an_error_with_non_primitive_code_and_message_falls_back_to_unknown(): void
    {
        $this->enqueueJson(409, '{"error":{"code":["nested"],"message":["nested"]}}');

        try {
            $this->client()->tasks()->get('t');
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame('unknown_error', $e->apiCode);
            self::assertSame('http 409', $e->getMessage());
        }
    }

    public function test_upserting_a_null_worker_defaults_to_an_empty_body(): void
    {
        $this->enqueueJson(200, '{"id":"w_auto"}');

        $id = $this->client()->workers()->upsert(null);

        self::assertSame('w_auto', $id);
        self::assertSame([], $this->requestBodyJson($this->lastRequest()));
    }

    public function test_a_decision_candidate_without_a_worker_id_still_parses(): void
    {
        $this->enqueueJson(200, '{"decisions":[{"id":"d1","taskId":"t1","workerId":"a1",'
            . '"matchedAt":1,"mode":"first-come","candidates":[{"score":3}]}]}');

        $decisions = $this->client()->decisions()->list();

        self::assertCount(1, $decisions);
        self::assertNull($decisions[0]->candidates[0]->workerId);
        self::assertSame(['score' => 3], $decisions[0]->candidates[0]->detail);
    }

    public function test_a_non_integer_quota_header_value_is_ignored_safely(): void
    {
        // One valid header (so quota is populated) + one malformed value (must not throw).
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}', [
            'X-Quota-Workers-Limit' => '50',
            'X-Quota-Task-Rate-Limit' => 'not-a-number',
        ]);

        $client = $this->client();
        $client->tasks()->create(new CreateTask(['x']));

        self::assertNotNull($client->getQuota());
        self::assertSame(50, $client->getQuota()->workersLimit);
        self::assertNull($client->getQuota()->taskRateLimit); // malformed value parsed to null
    }

    public function test_a_response_without_quota_headers_leaves_quota_null(): void
    {
        $this->enqueueJson(200, '{"workers":[],"count":0}');

        $client = $this->client();
        $client->workers()->list();

        self::assertNull($client->getQuota());
    }

    public function test_task_model_preserves_result_and_archived_fields_on_archived_reads(): void
    {
        $this->enqueueJson(200, '{"id":"task_old","tags":["x"],"priority":null,"status":"completed",'
            . '"workerId":"agent_1","createdAt":1,"meta":null,"result":{"resolved":true},"archived":true}');

        $task = $this->client()->tasks()->get('task_old');

        self::assertTrue($task->archived);
        self::assertSame(['resolved' => true], $task->result);
    }

    public function test_quota_info_reports_has_any(): void
    {
        self::assertFalse((new QuotaInfo())->hasAny());
        self::assertTrue((new QuotaInfo(taskRateLimit: 300))->hasAny());
        self::assertTrue((new QuotaInfo(taskRateRemaining: 1))->hasAny());
        self::assertTrue((new QuotaInfo(queuedTasksLimit: 1))->hasAny());
        self::assertTrue((new QuotaInfo(queuedTasksRemaining: 1))->hasAny());
        self::assertTrue((new QuotaInfo(workersLimit: 1))->hasAny());
        self::assertTrue((new QuotaInfo(workersRemaining: 1))->hasAny());
    }

    public function test_quota_info_from_headers_with_case_insensitive_lookup(): void
    {
        $quota = QuotaInfo::fromHeaders(['X-QUOTA-WORKERS-LIMIT' => ['42']]);

        self::assertNotNull($quota);
        self::assertSame(42, $quota->workersLimit);
        self::assertTrue($quota->hasAny());
    }

    public function test_quota_info_from_headers_accepts_a_scalar_header_value(): void
    {
        // Direct callers may pass a plain string value (not a list); it must parse the same.
        $quota = QuotaInfo::fromHeaders(['x-quota-workers-limit' => '42']);

        self::assertNotNull($quota);
        self::assertSame(42, $quota->workersLimit);
    }

    public function test_upsert_worker_id_setter(): void
    {
        $worker = (new UpsertWorker())->id('agent_1');

        self::assertSame(['id' => 'agent_1'], $worker->toArray());
    }
}
