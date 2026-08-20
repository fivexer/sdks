<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerException;
use Fivexer\SDK\Webhook\Webhook;
use PHPUnit\Framework\TestCase;

/**
 * Webhook signature verification: replay window, malformed headers, wrong secret,
 * tampered body, custom tolerance, and the platform header constant.
 */
final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_testsecret';
    private const NOW = 1750000000000;

    private static function body(): string
    {
        return '{"event":"task.matched","taskId":"task_8fk2","workerId":"agent_1"}';
    }

    private static function sign(string $secret, int $timestamp, string $body): string
    {
        return 't=' . $timestamp . ',v1=' . \hash_hmac('sha256', $timestamp . '.' . $body, $secret);
    }

    public function test_a_validly_signed_webhook_is_verified_and_parsed(): void
    {
        $raw = self::body();
        $header = self::sign(self::SECRET, self::NOW, $raw);

        $event = Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);

        self::assertSame('task.matched', $event->event);
        self::assertSame('task_8fk2', $event->data['taskId']);
        self::assertSame('agent_1', $event->data['workerId']);
    }

    public function test_a_webhook_signed_with_the_wrong_secret_is_rejected(): void
    {
        $raw = self::body();
        $header = self::sign('whsec_wrong', self::NOW, $raw);

        $this->expectException(FivexerException::class);
        Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
    }

    public function test_a_webhook_outside_the_replay_window_is_rejected(): void
    {
        $raw = self::body();
        $header = self::sign(self::SECRET, self::NOW - (10 * 60 * 1000), $raw); // 10 min ago

        $this->expectException(FivexerException::class);
        Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
    }

    public function test_a_tampered_body_fails_verification(): void
    {
        $raw = self::body();
        $header = self::sign(self::SECRET, self::NOW, $raw);
        $tampered = \str_replace('task_8fk2', 'task_9999', $raw);

        $this->expectException(FivexerException::class);
        Webhook::constructEvent($tampered, $header, self::SECRET, nowMs: self::NOW);
    }

    public function test_a_custom_tolerance_window_can_be_widened(): void
    {
        $raw = self::body();
        $header = self::sign(self::SECRET, self::NOW - (6 * 60 * 1000), $raw); // 6 min old

        // default tolerance (5 min) rejects
        try {
            Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
            self::fail('expected rejection with default tolerance');
        } catch (FivexerException) {
        }
        // widened tolerance (10 min) accepts
        $event = Webhook::constructEvent($raw, $header, self::SECRET, toleranceMs: 10 * 60 * 1000, nowMs: self::NOW);
        self::assertSame('task.matched', $event->event);
    }

    public function test_malformed_signature_headers_are_rejected(): void
    {
        $raw = self::body();
        $malformed = [
            '',
            'garbage',
            't=abc,v1=def',
            't=123',
            'v1=abc',
            't=123,v1=' . \str_repeat('x', 64),
            'x=1,v1=' . \str_repeat('0', 64),
        ];
        foreach ($malformed as $bad) {
            self::assertFalse(
                Webhook::verifySignature($raw, $bad, self::SECRET, nowMs: self::NOW),
                "expected rejection for header: {$bad}",
            );
        }
    }

    public function test_a_null_webhook_header_is_rejected(): void
    {
        self::assertFalse(Webhook::verifySignature('{}', '', self::SECRET, nowMs: self::NOW));
    }

    public function test_a_webhook_with_correct_t_part_but_wrong_v_prefix_is_rejected(): void
    {
        $header = 't=' . self::NOW . ',v2=' . \str_repeat('0', 64);
        self::assertFalse(Webhook::verifySignature('{}', $header, self::SECRET, nowMs: self::NOW));
    }

    public function test_a_webhook_with_an_empty_timestamp_is_rejected(): void
    {
        $header = 't=,v1=' . \str_repeat('0', 64);
        self::assertFalse(Webhook::verifySignature('{}', $header, self::SECRET, nowMs: self::NOW));
    }

    public function test_a_payload_without_an_event_key_yields_null_event(): void
    {
        $raw = '{"taskId":"task_1","workerId":"a1"}';
        $header = self::sign(self::SECRET, self::NOW, $raw);

        $event = Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
        self::assertNull($event->event);
        self::assertSame('task_1', $event->data['taskId']);
    }

    public function test_a_webhook_payload_with_a_non_primitive_event_yields_null_event(): void
    {
        $raw = '{"event":[1,2]}';
        $header = self::sign(self::SECRET, self::NOW, $raw);

        $event = Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
        self::assertNull($event->event);
        self::assertNotNull($event->data);
    }

    public function test_a_non_object_webhook_payload_yields_null_event_and_data(): void
    {
        $raw = '[1,2,3]';
        $header = self::sign(self::SECRET, self::NOW, $raw);

        $event = Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
        self::assertNull($event->event);
        self::assertNull($event->data);
    }

    public function test_a_non_json_webhook_payload_is_rejected_with_a_clear_error(): void
    {
        $raw = 'not json';
        $header = self::sign(self::SECRET, self::NOW, $raw);

        // signature passes, then parsing must fail clearly
        $this->expectException(FivexerException::class);
        Webhook::constructEvent($raw, $header, self::SECRET, nowMs: self::NOW);
    }

    public function test_signature_header_constant_is_the_platform_value(): void
    {
        self::assertSame('x-fivexer-signature', Webhook::SIGNATURE_HEADER);
    }

    public function test_verify_signature_returns_false_without_raising_for_garbage(): void
    {
        self::assertFalse(Webhook::verifySignature('{}', 'garbage', self::SECRET, nowMs: self::NOW));
    }
}
