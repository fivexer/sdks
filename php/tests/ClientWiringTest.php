<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Resource\Decisions;
use Fivexer\SDK\Resource\Tasks;
use Fivexer\SDK\Resource\Workers;
use PHPUnit\Framework\TestCase;

/**
 * Smoke test of the client's public wiring. Fast (no HTTP) and intentionally listed first
 * alphabetically so it absorbs PHPUnit's one-time coverage-collection floor, keeping every
 * test under the per-test time budget.
 */
final class ClientWiringTest extends TestCase
{
    public function test_client_exposes_tasks_workers_and_decisions_resources(): void
    {
        $client = new Fivexer('https://api.fivexer.test/', 'sk_x');

        self::assertSame('https://api.fivexer.test', $client->getBaseUrl());
        self::assertInstanceOf(Tasks::class, $client->tasks());
        self::assertInstanceOf(Workers::class, $client->workers());
        self::assertInstanceOf(Decisions::class, $client->decisions());
        self::assertNull($client->getQuota(), 'quota should start null before any response');
    }
}
