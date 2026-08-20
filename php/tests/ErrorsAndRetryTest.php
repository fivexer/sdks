<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;

/**
 * Error handling, quota-header parsing, transport retry, and constructor validation.
 */
final class ErrorsAndRetryTest extends ClientTestCase
{
    public function test_quota_headers_on_a_successful_response_are_exposed_on_the_client(): void
    {
        $this->enqueueJson(202, '{"id":"task_8fk2","status":"queued"}', [
            'X-Quota-Task-Rate-Limit' => '300',
            'X-Quota-Task-Rate-Remaining' => '299',
            'X-Quota-Queued-Tasks-Limit' => '50000',
            'X-Quota-Queued-Tasks-Remaining' => '49999',
        ]);

        $client = $this->client();
        $client->tasks()->create(new CreateTask(['english']));

        $quota = $client->getQuota();
        self::assertNotNull($quota);
        self::assertSame(300, $quota->taskRateLimit);
        self::assertSame(299, $quota->taskRateRemaining);
        self::assertSame(50000, $quota->queuedTasksLimit);
        self::assertSame(49999, $quota->queuedTasksRemaining);
        self::assertNull($quota->workersLimit);
    }

    public function test_a_rate_limited_request_raises_with_retry_after_and_quota_attached(): void
    {
        $this->enqueueJson(429, '{"error":{"code":"rate_limited","message":"task creation rate above 300/min"}}', [
            'Retry-After' => '60',
            'X-Quota-Task-Rate-Limit' => '300',
            'X-Quota-Task-Rate-Remaining' => '0',
        ]);

        try {
            $this->client()->tasks()->create(new CreateTask(['x']));
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame(429, $e->statusCode);
            self::assertSame('rate_limited', $e->apiCode);
            self::assertSame(60.0, $e->retryAfterSeconds);
            self::assertSame(0, $e->quota?->taskRateRemaining);
        }
    }

    public function test_an_invalid_retry_after_value_yields_null(): void
    {
        $this->enqueueJson(429, '{"error":{"code":"rate_limited","message":"slow down"}}', [
            'Retry-After' => 'not-a-number',
        ]);

        try {
            $this->client()->tasks()->list();
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertNull($e->retryAfterSeconds);
        }
    }

    public function test_a_validation_error_reports_the_code_and_message(): void
    {
        $this->enqueueJson(400, '{"error":{"code":"validation_failed",'
            . '"message":"body must have required property \'tags\'"}}');

        try {
            $this->client()->tasks()->list();
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame(400, $e->statusCode);
            self::assertSame('validation_failed', $e->apiCode);
            self::assertStringContainsString('tags', $e->getMessage());
        }
    }

    public function test_an_unparseable_error_body_falls_back_to_unknown_code(): void
    {
        $this->enqueueJson(500, '<html>oops</html>');

        try {
            $this->client()->tasks()->list();
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            self::assertSame(500, $e->statusCode);
            self::assertSame('unknown_error', $e->apiCode);
        }
    }

    public function test_a_204_response_has_no_body(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->tasks()->cancel('task_8fk2');

        self::assertSame(1, \count($this->history));
    }

    public function test_retry_transparently_retries_once_on_5xx_then_succeeds(): void
    {
        $this->enqueueJson(503, '{"error":{"code":"internal_error","message":"transient"}}');
        $this->enqueueJson(200, '{"tasks":[],"nextCursor":null,"hasMore":false}');

        $page = $this->client(maxRetries: 1)->tasks()->list();

        self::assertFalse($page->hasMore);
        self::assertCount(2, $this->history);
    }

    public function test_retry_sleeps_for_a_positive_retry_after_then_succeeds(): void
    {
        // A tiny positive retry-after exercises the real sleep path (1ms, well within budget).
        $this->enqueueJson(429, '{"error":{"code":"rate_limited","message":"slow down"}}', ['Retry-After' => '0.001']);
        $this->enqueueJson(200, '{"workers":[],"count":0}');

        $list = $this->client(maxRetries: 1)->workers()->list();

        self::assertSame(0, $list->count);
        self::assertCount(2, $this->history);
    }

    public function test_retry_does_not_retry_when_max_retries_is_zero(): void
    {
        $this->enqueueJson(503, '{"error":{"code":"internal_error","message":"down"}}');

        try {
            $this->client()->tasks()->list();
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException) {
            self::assertCount(1, $this->history);
        }
    }

    public function test_a_retried_post_sends_the_same_idempotency_key_both_times(): void
    {
        // First attempt: 503 (retryable). Second attempt: 202 (success).
        $this->enqueueJson(503, '{"error":{"code":"internal_error","message":"transient"}}');
        $this->enqueueJson(202, '{"id":"task_1","status":"queued"}');

        $this->client(maxRetries: 1)->tasks()->create(new CreateTask(['english']));

        // Both requests must carry the SAME idempotency key so the server can deduplicate.
        self::assertCount(2, $this->history);
        $key1 = $this->history[0]['request']->getHeaderLine('Idempotency-Key');
        $key2 = $this->history[1]['request']->getHeaderLine('Idempotency-Key');
        self::assertNotSame('', $key1);
        self::assertSame($key1, $key2, 'idempotency key must be stable across retries');
    }

    public function test_constructor_rejects_missing_base_url(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Fivexer('', 'sk_x');
    }

    public function test_constructor_rejects_missing_api_key(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new Fivexer('https://api.fivexer.test', '');
    }

    public function test_api_exception_to_string_includes_status_and_code(): void
    {
        $this->enqueueJson(404, '{"error":{"code":"not_found","message":"task not found"}}');

        try {
            $this->client()->tasks()->get('missing');
            self::fail('expected FivexerApiException');
        } catch (FivexerApiException $e) {
            $s = (string) $e;
            self::assertStringContainsString('404', $s);
            self::assertStringContainsString('not_found', $s);
        }
    }
}
