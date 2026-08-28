<?php

declare(strict_types=1);

namespace Fivexer\SDK;

use Fivexer\SDK\Exception\FivexerException;
use Fivexer\SDK\Internal\SessionTransport;
use Fivexer\SDK\Model\AcceptSupervisorInvite;
use Fivexer\SDK\Model\PushConfig;
use Fivexer\SDK\Model\PushSubscriptionInput;
use Fivexer\SDK\Model\SupervisorAssignResult;
use Fivexer\SDK\Model\SupervisorAvailabilityResult;
use Fivexer\SDK\Model\SupervisorEntry;
use Fivexer\SDK\Model\SupervisorMe;
use Fivexer\SDK\Model\SupervisorOverview;
use Fivexer\SDK\Model\VoiceIceServers;
use Fivexer\SDK\Model\SupervisorSession;
use Fivexer\SDK\Model\TaskAction;
use Fivexer\SDK\Model\TaskPriority;
use Fivexer\SDK\Model\UnparkTask;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Supervisor-facing client — the `sv_` session plane.
 *
 * A third credential type alongside {@see Fivexer} (workspace `sk_` key) and
 * {@see FivexerWorker} (worker `wt_` token). A supervisor watches and unblocks work rather than
 * doing it: they can unpark a task, reprioritise it, hand it to a crew member and pause one;
 * they can never create work, manage the roster, or reach the workspace plane. Scope is either
 * one team or the whole workspace, and every action is checked against it server-side — a crew
 * lead cannot push their crew's work onto another crew, or pull another crew's work in.
 *
 * Unlike the worker plane there is no login and no refresh. A session begins by redeeming a
 * single-use link an owner generated in the console, and ends when it expires or is revoked.
 * There is nothing to rotate with, so an expired session means "get a new link" — this client
 * deliberately has no recovery path, because inventing one would only hide that.
 */
final class FivexerSupervisor
{
    use SessionTransport;

    private readonly string $baseUrl;
    private readonly ClientInterface $httpClient;
    private ?string $token;
    private ?int $expiresAt;

    public function __construct(
        string $baseUrl,
        ?string $token = null,
        ?ClientInterface $httpClient = null,
        private readonly int $maxRetries = 1,
        ?int $expiresAt = null,
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }
        $this->baseUrl = \rtrim($baseUrl, '/');
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->httpClient = $httpClient ?? new Client();
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** The current supervisor session token, if one has been adopted. */
    public function getSessionToken(): ?string
    {
        return $this->token;
    }

    /** When the current token stops working — epoch-milliseconds, as the wire sends it. */
    public function getSessionExpiresAt(): ?int
    {
        return $this->expiresAt;
    }

    /** Adopt a supervisor token from a prior session. */
    public function setToken(?string $token, ?int $expiresAt = null): void
    {
        $this->token = $token;
        $this->expiresAt = $expiresAt;
    }

    // ---- session ----

    /** Where a supervisor can sign in from. Unauthenticated. */
    public function entry(): SupervisorEntry
    {
        return SupervisorEntry::fromArray($this->request('GET', '/supervisor-auth/entry') ?? []);
    }

    /**
     * Redeem a supervisor link, adopting the returned session.
     *
     * The token is single-use, and every way of failing answers identically — see
     * {@see AcceptSupervisorInvite}.
     */
    public function acceptInvite(AcceptSupervisorInvite $input): SupervisorSession
    {
        $session = SupervisorSession::fromArray(
            $this->request('POST', '/supervisor-auth/accept', $input->toArray()) ?? []
        );
        $this->setToken($session->token, $session->expiresAt);
        return $session;
    }

    /**
     * End the session and forget the token.
     *
     * Also drops this supervisor's push subscriptions server-side: a handed-over phone must stop
     * buzzing with another crew's work, so signing out and unsubscribing are one trust boundary
     * rather than two steps a caller can get half-right.
     */
    public function logout(): void
    {
        $this->request('POST', '/supervisor-auth/logout');
        $this->setToken(null, null);
    }

    // ---- board ----

    /** The signed-in supervisor and their scope. A null `teamKey` means the whole workspace. */
    public function me(): SupervisorMe
    {
        return SupervisorMe::fromArray($this->request('GET', '/supervisor/me') ?? []);
    }

    /** The whole board in one request: counts, crew (busiest first) and parked work. */
    public function overview(): SupervisorOverview
    {
        return SupervisorOverview::fromArray($this->request('GET', '/supervisor/overview') ?? []);
    }

    // ---- actions ----

    /**
     * Return a parked task to the queue. Reset the clocks that parked it, or the next sweep may
     * park it straight back.
     */
    public function unpark(string $taskId, ?UnparkTask $options = null): TaskAction
    {
        return TaskAction::fromArray(
            $this->request(
                'POST',
                '/supervisor/tasks/' . \rawurlencode($taskId) . '/unpark',
                $options === null ? [] : $options->toArray()
            ) ?? []
        );
    }

    public function setPriority(string $taskId, float $priority): TaskPriority
    {
        return TaskPriority::fromArray(
            $this->request(
                'POST',
                '/supervisor/tasks/' . \rawurlencode($taskId) . '/priority',
                ['priority' => $priority]
            ) ?? []
        );
    }

    /**
     * Hand a task to a specific crew member.
     *
     * Both ends are scope-checked: a task from another crew, or a worker outside this one, is a
     * 403 rather than a silent move. A refusal from the matcher (paused, backlog full, prior
     * rejection) surfaces as a 400 `assign_blocked`; `force` bypasses those checks but never
     * worker existence.
     */
    public function assign(string $taskId, string $workerId, ?bool $force = null): SupervisorAssignResult
    {
        $body = ['workerId' => $workerId];
        if ($force !== null) {
            $body['force'] = $force;
        }
        return SupervisorAssignResult::fromArray(
            $this->request('POST', '/supervisor/tasks/' . \rawurlencode($taskId) . '/assign', $body) ?? []
        );
    }

    /**
     * Pause or resume a crew member.
     *
     * Pausing never releases work implicitly — that is the engine's rule. `releaseBacklog` is the
     * explicit redistribution move, and only *pending* work moves; anything already accepted
     * stays with whoever accepted it.
     */
    public function setAvailability(
        string $workerId,
        bool $available,
        ?bool $releaseBacklog = null,
    ): SupervisorAvailabilityResult {
        $body = ['available' => $available];
        if ($releaseBacklog !== null) {
            $body['releaseBacklog'] = $releaseBacklog;
        }
        return SupervisorAvailabilityResult::fromArray(
            $this->request(
                'POST',
                '/supervisor/workers/' . \rawurlencode($workerId) . '/availability',
                $body
            ) ?? []
        );
    }

    // ---- push ----

    /**
     * Read before prompting for notification permission: `enabled: false` means this deployment
     * has no VAPID keypair, and a browser only gives you one prompt.
     */
    /**
     * **Experimental — voice is not production-ready.** This surface may change or be withdrawn
     * in a patch release; do not build on it yet.
     *
     * STUN/TURN servers for a call. Same contract as the worker plane's: fetched per call
     * because a TURN credential is short-lived, and 404 `voice_disabled` where the workspace has
     * no voice.
     */
    public function voiceIce(): VoiceIceServers
    {
        return VoiceIceServers::fromArray($this->request('GET', '/supervisor/voice/ice') ?? []);
    }

    public function pushConfig(): PushConfig
    {
        return PushConfig::fromArray($this->request('GET', '/supervisor/push/config') ?? []);
    }

    /**
     * Register a browser subscription against this supervisor's own table.
     *
     * Returns the server's bare acknowledgement rather than a subscription record — the
     * supervisor table is keyed by endpoint and has nothing else to hand back, which is where
     * this differs from the worker plane's equivalent.
     */
    public function pushSubscribe(PushSubscriptionInput $subscription): bool
    {
        $data = $this->request('POST', '/supervisor/push/subscriptions', $subscription->toArray()) ?? [];
        return (bool) ($data['ok'] ?? false);
    }

    /** Unregister. Endpoint only — a browser discards the keys before it tells you. */
    public function pushUnsubscribe(string $endpoint): void
    {
        $this->request('DELETE', '/supervisor/push/subscriptions', ['endpoint' => $endpoint]);
    }

    // ---- internals ----

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $path, ?array $body = null): ?array
    {
        $url = $this->baseUrl . '/v1' . $path;
        $attempt = 0;

        while (true) {
            $headers = ['Accept' => 'application/json'];
            // `entry` and `accept` run before a token exists; sending an empty bearer would be a
            // malformed request rather than an anonymous one.
            if ($this->token !== null) {
                $headers['Authorization'] = 'Bearer ' . $this->token;
            }
            if ($body !== null) {
                $headers['Content-Type'] = 'application/json';
            }

            $options = [RequestOptions::HTTP_ERRORS => false, RequestOptions::HEADERS => $headers];
            if ($body !== null) {
                // PHP has one array type, so json_encode([]) emits `[]` and a Fastify body
                // schema rejects it. Endpoints whose body is entirely optional — unpark with no
                // flags — are the ones that hit this.
                $options[RequestOptions::JSON] = $body === [] ? new \stdClass() : $body;
            }

            try {
                $response = $this->httpClient->request($method, $url, $options);
            } catch (GuzzleException $e) {
                throw new FivexerException('HTTP request failed: ' . $e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            $retryAfter = $response->getHeaderLine('retry-after');

            // Reads only: a supervisor action carries no idempotency key, so a replayed assign
            // could move a task twice.
            if ($attempt < $this->maxRetries && $method === 'GET' && self::isRetryable($status)) {
                $wait = self::retryAfterSeconds($retryAfter);
                if ($wait !== null && $wait > 0) {
                    \usleep((int) ($wait * 1_000_000));
                }
                $attempt++;
                continue;
            }

            $raw = (string) $response->getBody();
            if ($status >= 400) {
                throw self::toApiException($status, $raw);
            }
            if ($status === 204 || $raw === '') {
                return null;
            }

            /** @var array<string, mixed>|null $decoded */
            $decoded = \json_decode($raw, true);
            return \is_array($decoded) ? $decoded : null;
        }
    }
}
