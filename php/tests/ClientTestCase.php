<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\FivexerWorker;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

/**
 * Shared base: wires the Fivexer client to Guzzle's MockHandler and records every
 * request via the history middleware. Black-box — tests drive the public SDK API and
 * inspect the exact HTTP the SDK emits (method, path, headers, body) since that wire
 * shape *is* the /v1 contract, not an implementation detail.
 */
abstract class ClientTestCase extends TestCase
{
    protected MockHandler $mock;

    /** @var array<int, array{request: RequestInterface, response: Response}> */
    protected array $history = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->mock = new MockHandler();
        $this->history = [];
    }

    protected function client(int $maxRetries = 0): Fivexer
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        return new Fivexer('https://api.fivexer.test', 'sk_test_abc123', new Client(['handler' => $stack]), $maxRetries);
    }

    /** A worker-portal client already holding a session token, as if login() had run. */
    protected function worker(int $maxRetries = 0): FivexerWorker
    {
        return new FivexerWorker(
            'https://api.fivexer.test',
            'wt_s3ss10n',
            'agent_1',
            $this->guzzle(),
            $maxRetries,
        );
    }

    /** A worker-portal client with no token yet — the pre-login state. */
    protected function anonymousWorker(int $maxRetries = 0): FivexerWorker
    {
        return new FivexerWorker('https://api.fivexer.test', null, null, $this->guzzle(), $maxRetries);
    }

    private function guzzle(): Client
    {
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));
        return new Client(['handler' => $stack]);
    }

    /** The request path with any query string stripped. */
    protected function pathOf(RequestInterface $request): string
    {
        return $request->getUri()->getPath();
    }

    /** The raw query string of a request, or '' when it had none. */
    protected function queryOf(RequestInterface $request): string
    {
        return $request->getUri()->getQuery();
    }

    /** Every recorded request in order, for multi-hop flows like an attachment upload. */
    /** @return list<RequestInterface> */
    protected function recordedRequests(): array
    {
        return \array_map(static fn (array $entry): RequestInterface => $entry['request'], $this->history);
    }

    protected function enqueueJson(int $status, string $json, array $headers = []): void
    {
        $this->mock->append(new Response($status, $headers, $json));
    }

    protected function enqueueEmpty(int $status, array $headers = []): void
    {
        $this->mock->append(new Response($status, $headers));
    }

    protected function lastRequest(): RequestInterface
    {
        self::assertNotEmpty($this->history, 'expected at least one recorded request');
        return $this->history[\array_key_last($this->history)]['request'];
    }

    protected function requestBody(RequestInterface $request): string
    {
        return (string) $request->getBody();
    }

    /** @return array<string, mixed> */
    protected function requestBodyJson(RequestInterface $request): array
    {
        $decoded = \json_decode($this->requestBody($request), true);
        self::assertIsArray($decoded);
        return $decoded;
    }
}
