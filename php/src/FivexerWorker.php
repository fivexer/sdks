<?php

declare(strict_types=1);

namespace Fivexer\SDK;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Exception\FivexerException;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Internal\SessionTransport;
use Fivexer\SDK\Model\AcceptWorkerInvite;
use Fivexer\SDK\Model\AcceptWorkerInviteResult;
use Fivexer\SDK\Model\ChangePin;
use Fivexer\SDK\Model\Comment;
use Fivexer\SDK\Model\CommentPage;
use Fivexer\SDK\Model\JoinWorkspace;
use Fivexer\SDK\Model\JoinWorkspaceResult;
use Fivexer\SDK\Model\PushConfig;
use Fivexer\SDK\Model\PushSubscription;
use Fivexer\SDK\Model\PushSubscriptionInput;
use Fivexer\SDK\Model\Skill;
use Fivexer\SDK\Model\TaskAction;
use Fivexer\SDK\Model\TeamPresence;
use Fivexer\SDK\Model\WorkerAvailabilityState;
use Fivexer\SDK\Model\WorkerBreakEnded;
use Fivexer\SDK\Model\WorkerBreakStarted;
use Fivexer\SDK\Model\WorkerBreakToday;
use Fivexer\SDK\Model\WorkerDevice;
use Fivexer\SDK\Model\WorkerDeviceInput;
use Fivexer\SDK\Model\WorkerLocation;
use Fivexer\SDK\Model\WorkerLocationResult;
use Fivexer\SDK\Model\WorkerLogin;
use Fivexer\SDK\Model\WorkerMe;
use Fivexer\SDK\Model\WorkerMetricsToday;
use Fivexer\SDK\Model\WorkerMetricsWindow;
use Fivexer\SDK\Model\WorkerQueue;
use Fivexer\SDK\Model\WorkerSessionToken;
use Fivexer\SDK\Model\WorkerSkillLevel;
use Fivexer\SDK\Model\WorkerSkillSet;
use Fivexer\SDK\Model\WorkerTaskDetail;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\RequestOptions;

/**
 * Worker-facing client for the hosted portal — the `wt_` worker-token plane.
 *
 * Deliberately separate from {@see Fivexer} (workspace `sk_` key): a worker token is scoped to
 * exactly one worker and can never reach task-creation or worker-management routes, so the type
 * system says so too.
 *
 * {@see self::login()} adopts both the returned token and the worker id, so later calls need no
 * extra wiring:
 *
 * <code>
 * $worker = new FivexerWorker('https://api.fivexer.com');
 * $worker->login(new WorkerLogin('ws_1', 'agent_1', '4821'));
 * $queue = $worker->queue();
 * $worker->accept($queue->taskIds[0]);
 * </code>
 *
 * Unlike the workspace plane this surface emits no X-Quota-* headers and carries no idempotency
 * key, so only reads are retried — replaying an accept could claim a task twice.
 */
final class FivexerWorker
{
    use SessionTransport;

    private const DEFAULT_TIMEOUT_SECONDS = 30.0;

    private readonly string $baseUrl;
    private readonly ClientInterface $httpClient;
    private readonly int $maxRetries;

    private ?string $token;
    private ?string $workerId;

    public function __construct(
        string $baseUrl,
        ?string $token = null,
        ?string $workerId = null,
        ?ClientInterface $httpClient = null,
        int $maxRetries = 1,
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }
        $this->baseUrl = \rtrim($baseUrl, '/');
        $this->token = $token;
        $this->workerId = $workerId;
        $this->maxRetries = $maxRetries;
        $this->httpClient = $httpClient ?? new Client(['timeout' => self::DEFAULT_TIMEOUT_SECONDS]);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** The current worker session token, if logged in. */
    public function getSessionToken(): ?string
    {
        return $this->token;
    }

    /** The worker this client acts as. */
    public function getWorkerId(): ?string
    {
        return $this->workerId;
    }

    /** Adopt a token (and optionally the worker id it belongs to) from a prior session. */
    public function setToken(?string $token, ?string $workerId = null): void
    {
        $this->token = $token;
        if ($workerId !== null) {
            $this->workerId = $workerId;
        }
    }

    // ---- auth ----

    /** Log in and adopt the returned token plus the worker id for subsequent calls. */
    public function login(WorkerLogin $credentials): WorkerSessionToken
    {
        $session = WorkerSessionToken::fromArray(
            $this->request('POST', '/worker-auth/login', $credentials->toArray()) ?? []
        );
        $this->token = $session->token;
        $this->workerId = $credentials->getWorkerId();
        return $session;
    }

    /** Revoke the session and forget the token. */
    public function logout(): void
    {
        $this->request('POST', '/worker-auth/logout', []);
        $this->token = null;
    }

    // ---- tasks ----

    /** This worker's current queue. */
    public function queue(?string $forWorkerId = null): WorkerQueue
    {
        $path = '/portal/workers/' . \rawurlencode($this->require($forWorkerId)) . '/queue';
        return WorkerQueue::fromArray($this->request('GET', $path) ?? []);
    }

    /** Rich detail of a task assigned to this worker; another worker's task answers 404. */
    public function taskDetail(string $taskId): WorkerTaskDetail
    {
        return WorkerTaskDetail::fromArray(
            $this->request('GET', '/portal/tasks/' . \rawurlencode($taskId)) ?? []
        );
    }

    public function accept(string $taskId, ?string $forWorkerId = null): TaskAction
    {
        return $this->action($taskId, 'accept', $forWorkerId);
    }

    /** Reject a task — it requeues for other eligible workers. */
    public function reject(string $taskId, ?string $forWorkerId = null): TaskAction
    {
        return $this->action($taskId, 'reject', $forWorkerId);
    }

    /** @param array<string, mixed>|null $result */
    public function complete(string $taskId, ?array $result = null, ?string $forWorkerId = null): TaskAction
    {
        $body = ['workerId' => $this->require($forWorkerId)];
        if ($result !== null) {
            $body['result'] = $result;
        }
        return TaskAction::fromArray(
            $this->request('POST', '/portal/tasks/' . \rawurlencode($taskId) . '/complete', $body) ?? []
        );
    }

    private function action(string $taskId, string $verb, ?string $forWorkerId): TaskAction
    {
        return TaskAction::fromArray(
            $this->request(
                'POST',
                '/portal/tasks/' . \rawurlencode($taskId) . '/' . $verb,
                ['workerId' => $this->require($forWorkerId)]
            ) ?? []
        );
    }

    // ---- breaks, metrics, presence ----

    /**
     * Start a break: routing to this worker pauses while the current backlog is kept.
     * Answers 409 when a break is already open.
     */
    public function startBreak(?string $reason = null): WorkerBreakStarted
    {
        $body = $reason === null ? [] : ['reason' => $reason];
        return WorkerBreakStarted::fromArray($this->request('POST', '/portal/breaks/start', $body) ?? []);
    }

    /**
     * End the break and resume routing.
     *
     * Returns null when no break was open — the API answers 204, which is a normal outcome for
     * a portal UI polling this, not an error.
     */
    public function endBreak(): ?WorkerBreakEnded
    {
        $data = $this->request('POST', '/portal/breaks/end', []);
        return $data === null ? null : WorkerBreakEnded::fromArray($data);
    }

    /** This worker's own breaks for today, including the one still running. */
    public function breaksToday(): WorkerBreakToday
    {
        return WorkerBreakToday::fromArray($this->request('GET', '/portal/breaks/today') ?? []);
    }

    /** This worker's own metrics for today: completions, break time, working time. */
    public function metricsToday(): WorkerMetricsToday
    {
        return WorkerMetricsToday::fromArray($this->request('GET', '/portal/metrics/today') ?? []);
    }

    /** Read-only team presence — visible to every worker in the workspace. */
    public function teamPresence(): TeamPresence
    {
        return TeamPresence::fromArray($this->request('GET', '/portal/team/presence') ?? []);
    }

    // ---- internals ----

    /**
     * Resolve the worker to act as. Refusing locally rather than guessing is the point: a wrong
     * id here would let one worker act on another's queue.
     */
    // ---- sessions that mint a token ----

    /**
     * Rotate the session token in place.
     *
     * Returns false when the server says re-authenticate (any 4xx) — the caller's job there is a
     * login prompt, not an exception. Anything else (5xx, network, an older server with no
     * route) is raised, leaving the current token usable for the caller's own retry.
     */
    public function refresh(): bool
    {
        if ($this->token === null || $this->token === '') {
            return false;
        }
        try {
            $session = WorkerSessionToken::fromArray($this->request('POST', '/worker-auth/refresh') ?? []);
        } catch (FivexerApiException $e) {
            if ($e->statusCode >= 400 && $e->statusCode < 500) {
                return false;
            }
            throw $e;
        }
        $this->token = $session->token;
        return true;
    }

    /**
     * Self-register through a QR join link, adopting the returned session.
     *
     * The worker id is generated server-side — show it to them, it is the username they type at
     * the PIN screen next time. When the link required approval, `pendingApproval` is true and no
     * work routes until an operator admits them.
     */
    public function join(JoinWorkspace $input): JoinWorkspaceResult
    {
        $result = JoinWorkspaceResult::fromArray(
            $this->request('POST', '/worker-auth/join', $input->toArray()) ?? []
        );
        $this->setToken($result->token, $result->workerId);
        return $result;
    }

    /**
     * Consume an emailed invite token and set this worker's PIN, adopting the returned session.
     *
     * Setting a PIN is the sign-in — otherwise the worker's next act after choosing one would be
     * to retype it into a login form, which is what the magic link exists to avoid. The token is
     * single-use: replaying it is a 400 `invalid_token`, same as an expired link.
     */
    public function acceptInvite(AcceptWorkerInvite $input): AcceptWorkerInviteResult
    {
        $result = AcceptWorkerInviteResult::fromArray(
            $this->request('POST', '/worker-auth/accept-invite', $input->toArray()) ?? []
        );
        $this->setToken($result->token, $result->workerId);
        return $result;
    }

    // ---- shift and identity ----

    /**
     * Who am I, and am I on shift? Works on data-plane-only deployments, where the break and team
     * endpoints 501 (`onBreak` is simply always false there).
     */
    public function me(): WorkerMe
    {
        return WorkerMe::fromArray($this->request('GET', '/portal/me') ?? []);
    }

    /**
     * Go on or off shift — the worker's own switch. Workers are created off shift, so this is
     * what makes someone matchable in the first place. Off shift keeps the existing backlog;
     * going available also ends an open break. Throws 403 `approval_pending` while an operator
     * still has to admit a QR-join worker.
     */
    public function setAvailability(bool $available): WorkerAvailabilityState
    {
        return WorkerAvailabilityState::fromArray(
            $this->request('POST', '/portal/me/availability', ['available' => $available]) ?? []
        );
    }

    /** A wrong current PIN is a 400 (`invalid_current_pin`); the session survives either way. */
    public function changePin(ChangePin $input): void
    {
        $this->request('POST', '/portal/me/pin', $input->toArray());
    }

    // ---- own skills ----

    /**
     * The workspace's skill catalogue — what this worker may claim. Read-only: inventing a skill
     * is an operator's decision, so a worker picks from this list or picks nothing.
     *
     * @return list<Skill>
     */
    public function skillCatalog(): array
    {
        $data = $this->request('GET', '/portal/skills') ?? [];
        /** @var list<Skill> */
        return Json::parseEach($data, 'skills', [Skill::class, 'fromArray']);
    }

    /**
     * Declare what this worker can do, replacing their whole set. Does not change shift state:
     * `WorkerMe::$skillSetupPending` asks the question, this answers it, and going on shift stays
     * a separate claim.
     *
     * @param list<WorkerSkillLevel> $skills
     */
    public function setSkills(array $skills): WorkerSkillSet
    {
        return WorkerSkillSet::fromArray($this->request('PUT', '/portal/me/skills', [
            'skills' => \array_map(static fn (WorkerSkillLevel $s): array => $s->toArray(), $skills),
        ]) ?? []);
    }

    // ---- comments, metrics, location ----

    public function comments(string $taskId, ?string $cursor = null, ?int $limit = null): CommentPage
    {
        return CommentPage::fromArray(
            $this->request(
                'GET',
                '/portal/tasks/' . \rawurlencode($taskId) . '/comments',
                null,
                ['cursor' => $cursor, 'limit' => $limit]
            ) ?? []
        );
    }

    public function addComment(string $taskId, string $body): Comment
    {
        $data = $this->request(
            'POST',
            '/portal/tasks/' . \rawurlencode($taskId) . '/comments',
            ['body' => $body]
        ) ?? [];
        return Comment::fromArray($data['comment'] ?? []);
    }

    public function metricsWindow(string $window = '7d'): WorkerMetricsWindow
    {
        return WorkerMetricsWindow::fromArray(
            $this->request('GET', '/portal/metrics', null, ['window' => $window]) ?? []
        );
    }

    /** Rate-limited server-side (about one per 10s) to keep churn off the matching data plane. */
    public function updateLocation(WorkerLocation $location): WorkerLocationResult
    {
        return WorkerLocationResult::fromArray(
            $this->request('POST', '/portal/workers/me/location', $location->toArray()) ?? []
        );
    }

    // ---- push notifications ----

    /** Register an Expo push token for the native app. Browsers use pushSubscribe() instead. */
    public function registerDevice(WorkerDeviceInput $device): WorkerDevice
    {
        return WorkerDevice::fromArray($this->request('POST', '/portal/devices', $device->toArray()) ?? []);
    }

    /** Call on logout, or when notification permission is revoked. */
    public function unregisterDevice(string $deviceToken): void
    {
        $this->request('DELETE', '/portal/devices', ['token' => $deviceToken]);
    }

    /**
     * Read this before prompting for notification permission: `enabled: false` means the
     * deployment has no VAPID keypair, and a browser only gives you one prompt.
     */
    public function pushConfig(): PushConfig
    {
        return PushConfig::fromArray($this->request('GET', '/portal/push/config') ?? []);
    }

    public function pushSubscribe(PushSubscriptionInput $subscription): PushSubscription
    {
        return PushSubscription::fromArray(
            $this->request('POST', '/portal/push/subscriptions', $subscription->toArray()) ?? []
        );
    }

    /**
     * Endpoint only — by the time a browser fires `pushsubscriptionchange` it has already
     * discarded the keys, so requiring them would break the case this exists for.
     */
    public function pushUnsubscribe(string $endpoint): void
    {
        $this->request('DELETE', '/portal/push/subscriptions', ['endpoint' => $endpoint]);
    }

    private function require(?string $explicit): string
    {
        $resolved = $explicit ?? $this->workerId;
        if ($resolved === null || $resolved === '') {
            throw new FivexerApiException(
                400,
                'worker_id_required',
                'workerId is required — call login() first or pass it explicitly',
            );
        }
        return $resolved;
    }

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, mixed>|null Decoded JSON body, or null for 204 responses
     *
     * @throws FivexerApiException on non-2xx responses
     * @throws FivexerException on transport failures
     */
    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed>|null $query No worker-plane operation needed a query string
     *     until the self-service surface arrived, so this parameter is newer than the helper.
     * @return array<string, mixed>|null
     */
    private function request(string $method, string $path, ?array $body = null, ?array $query = null): ?array
    {
        $url = $this->baseUrl . '/v1' . $path;
        $attempt = 0;

        while (true) {
            $options = [RequestOptions::HTTP_ERRORS => false, RequestOptions::HEADERS => $this->headers($body)];
            if ($body !== null) {
                $options[RequestOptions::JSON] = $body === [] ? new \stdClass() : $body;
            }
            $filtered = $query === null ? [] : \array_filter($query, static fn (mixed $v): bool => $v !== null);
            if ($filtered !== []) {
                $options[RequestOptions::QUERY] = $filtered;
            }

            try {
                $response = $this->httpClient->request($method, $url, $options);
            } catch (GuzzleException $e) {
                throw new FivexerException('HTTP request failed: ' . $e->getMessage(), 0, $e);
            }

            $status = $response->getStatusCode();
            $retryAfter = $response->getHeaderLine('retry-after');

            // Reads only: portal actions carry no idempotency key, so a replayed accept could
            // claim a task twice.
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

    /**
     * @param array<string, mixed>|null $body
     * @return array<string, string>
     */
    private function headers(?array $body): array
    {
        $headers = ['Accept' => 'application/json'];
        // The login call runs before a token exists; sending an empty bearer would be a
        // malformed request rather than an anonymous one.
        if ($this->token !== null) {
            $headers['Authorization'] = 'Bearer ' . $this->token;
        }
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
        }
        return $headers;
    }

}
