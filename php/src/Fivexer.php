<?php

declare(strict_types=1);

namespace Fivexer\SDK;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Exception\FivexerException;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\Decision;
use Fivexer\SDK\Model\QuotaInfo;
use Fivexer\SDK\Model\Task;
use Fivexer\SDK\Model\TaskAction;
use Fivexer\SDK\Model\TaskPage;
use Fivexer\SDK\Model\UpsertWorker;
use Fivexer\SDK\Model\WorkerList;
use Fivexer\SDK\Model\WorkerQueue;
use Fivexer\SDK\Model\WorkspaceStats;
use Fivexer\SDK\Model\AttachmentUpload;
use Fivexer\SDK\Model\QueueAuditReport;
use Fivexer\SDK\Model\SlaStats;
use Fivexer\SDK\Model\WorkerPortalLink;
use Fivexer\SDK\Resource\Breaks;
use Fivexer\SDK\Resource\Decisions;
use Fivexer\SDK\Resource\History;
use Fivexer\SDK\Resource\Identities;
use Fivexer\SDK\Resource\JoinLinks;
use Fivexer\SDK\Resource\Learning;
use Fivexer\SDK\Resource\Notifications;
use Fivexer\SDK\Resource\Runs;
use Fivexer\SDK\Resource\Skills;
use Fivexer\SDK\Resource\Tasks;
use Fivexer\SDK\Resource\Team;
use Fivexer\SDK\Resource\Teams;
use Fivexer\SDK\Resource\Workers;
use Fivexer\SDK\Resource\Workflows;
use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\RequestOptions;
use Psr\Http\Message\ResponseInterface;

/**
 * Synchronous typed client for the Fivexer Platform /v1 API.
 *
 * Zero hard dependencies beyond Guzzle + ext-curl. Inject a Guzzle Client (with a
 * MockHandler) for testing.
 *
 * Mirrors the Python/Java SDKs exactly:
 *  - Constructor: {baseUrl, apiKey, httpClient?, maxRetries?}
 *  - Every request: Authorization: Bearer <key>, Accept: application/json
 *  - POST /tasks auto-sends an Idempotency-Key (UUID), stable across retries
 *  - Retry on 429/5xx honoring Retry-After (one retry by default)
 *  - Parse X-Quota-* on every response onto $client->quota; attach to errors too
 *  - Non-2xx -> FivexerApiException; 204 -> null
 *
 * <code>
 * $client = new Fivexer('https://api.fivexer.com', 'sk_test_...');
 * $client->workers()->upsert((new UpsertWorker('agent_1'))->tags(['english']));
 * $task = $client->tasks()->create((new CreateTask(['english']))->priority(90));
 * </code>
 */
final class Fivexer
{
    private const DEFAULT_TIMEOUT_SECONDS = 30.0;

    private readonly string $baseUrl;
    private readonly string $apiKey;
    private readonly ClientInterface $httpClient;
    private readonly int $maxRetries;

    private ?QuotaInfo $quota = null;
    private readonly Tasks $tasksResource;
    private readonly Workers $workersResource;
    private readonly Skills $skillsResource;
    private readonly Teams $teamsResource;
    private readonly JoinLinks $joinLinksResource;
    private readonly Identities $identitiesResource;
    private readonly Decisions $decisionsResource;
    private readonly Workflows $workflowsResource;
    private readonly Runs $runsResource;
    private readonly Learning $learningResource;
    private readonly Notifications $notificationsResource;
    private readonly History $historyResource;
    private readonly Team $teamResource;
    private readonly Breaks $breaksResource;

    public function __construct(
        string $baseUrl,
        string $apiKey,
        ?ClientInterface $httpClient = null,
        int $maxRetries = 1,
    ) {
        if ($baseUrl === '') {
            throw new \InvalidArgumentException('baseUrl is required');
        }
        if ($apiKey === '') {
            throw new \InvalidArgumentException('apiKey is required');
        }
        $this->baseUrl = \rtrim($baseUrl, '/');
        $this->apiKey = $apiKey;
        $this->maxRetries = $maxRetries;
        $this->httpClient = $httpClient ?? new Client(['timeout' => self::DEFAULT_TIMEOUT_SECONDS]);
        $this->tasksResource = new Tasks($this);
        $this->teamsResource = new Teams($this);
        $this->joinLinksResource = new JoinLinks($this);
        $this->identitiesResource = new Identities($this);
        $this->workersResource = new Workers($this);
        $this->skillsResource = new Skills($this);
        $this->decisionsResource = new Decisions($this);
        $this->workflowsResource = new Workflows($this);
        $this->runsResource = new Runs($this);
        $this->learningResource = new Learning($this);
        $this->notificationsResource = new Notifications($this);
        $this->historyResource = new History($this);
        $this->teamResource = new Team($this);
        $this->breaksResource = new Breaks($this);
    }

    public function getBaseUrl(): string
    {
        return $this->baseUrl;
    }

    /** Latest X-Quota-* snapshot seen on any response (success or error). */
    public function getQuota(): ?QuotaInfo
    {
        return $this->quota;
    }

    public function tasks(): Tasks
    {
        return $this->tasksResource;
    }

    public function workers(): Workers
    {
        return $this->workersResource;
    }

    public function teams(): Teams
    {
        return $this->teamsResource;
    }

    public function joinLinks(): JoinLinks
    {
        return $this->joinLinksResource;
    }

    public function identities(): Identities
    {
        return $this->identitiesResource;
    }

    public function skills(): Skills
    {
        return $this->skillsResource;
    }

    public function decisions(): Decisions
    {
        return $this->decisionsResource;
    }

    public function workflows(): Workflows
    {
        return $this->workflowsResource;
    }

    public function runs(): Runs
    {
        return $this->runsResource;
    }

    public function learning(): Learning
    {
        return $this->learningResource;
    }

    public function notifications(): Notifications
    {
        return $this->notificationsResource;
    }

    /** Historical stats from the archive. Requires the control plane. */
    public function history(): History
    {
        return $this->historyResource;
    }

    /** Supervisor presence view. Requires the control plane. */
    public function team(): Team
    {
        return $this->teamResource;
    }

    /** Supervisor break rollups. Requires the control plane. */
    public function breaks(): Breaks
    {
        return $this->breaksResource;
    }

    public function stats(): WorkspaceStats
    {
        return WorkspaceStats::fromArray($this->request('GET', '/stats') ?? []);
    }

    /** SLO counters, workspace-wide or for one tag. */
    public function slaStats(?string $tag = null): SlaStats
    {
        return SlaStats::fromArray($this->request('GET', '/stats/sla', null, ['tag' => $tag]) ?? []);
    }

    /**
     * Why the queue is not draining. Walks the queue against the live roster, so it is heavier
     * than {@see self::stats()} — poll it on a dashboard's cadence, not a request's.
     */
    public function queueAudit(
        ?int $limit = null,
        ?int $minWaitingMs = null,
        ?bool $includeHealthy = null,
    ): QueueAuditReport {
        return QueueAuditReport::fromArray($this->request('GET', '/stats/queue-audit', null, [
            'limit' => $limit,
            'minWaitingMs' => $minWaitingMs,
            // Fastify parses the string form; PHP would otherwise render false as the empty
            // string and true as "1", neither of which the server reads as a boolean.
            'includeHealthy' => $includeHealthy === null ? null : ($includeHealthy ? 'true' : 'false'),
        ]) ?? []);
    }

    /** Where workers log in, and whether the portal is switched on at all. */
    public function portal(): WorkerPortalLink
    {
        return WorkerPortalLink::fromArray($this->request('GET', '/portal') ?? []);
    }

    /**
     * PUT attachment bytes straight to object storage. Not a /v1 call: no API key, no retry,
     * and the presigned headers go out exactly as issued because they are part of the signature.
     *
     * @internal Used by the attachments upload helper.
     * @throws FivexerApiException when storage rejects the upload
     * @throws FivexerException on a transport failure
     */
    public function putBytes(AttachmentUpload $upload, string $payload): void
    {
        try {
            $response = $this->httpClient->request($upload->method, $upload->url, [
                RequestOptions::HEADERS => $upload->headers,
                RequestOptions::BODY => $payload,
                RequestOptions::HTTP_ERRORS => false,
            ]);
        } catch (GuzzleException $e) {
            throw new FivexerException('attachment upload failed: ' . $e->getMessage(), 0, $e);
        }

        $status = $response->getStatusCode();
        if ($status >= 400) {
            throw new FivexerApiException(
                $status,
                'upload_failed',
                'storage upload failed with http ' . $status,
            );
        }
    }

    /**
     * Core transport. Single entry point for every /v1 call; resource classes delegate here.
     *
     * @param string $method HTTP method
     * @param string $path Path under /v1, e.g. '/tasks'
     * @param array<string, mixed>|null $body JSON body to send, or null for none
     * @param array<string, string|int|null> $query Query parameters (null values skipped)
     * @return array<string, mixed>|null Decoded JSON body, or null for 204 responses
     *
     * @throws FivexerApiException on non-2xx responses
     * @throws FivexerException on transport failures
     */
    public function request(
        string $method,
        string $path,
        ?array $body = null,
        array $query = [],
    ): ?array {
        $url = $this->baseUrl . '/v1' . $path;
        $options = $this->buildOptions($method, $body, $query);

        $attempt = 0;
        while (true) {
            try {
                $response = $this->httpClient->request($method, $url, $options);
            } catch (RequestException $e) {
                $response = $e->getResponse();
                if ($response === null) {
                    throw new FivexerException('HTTP request failed: ' . $e->getMessage(), 0, $e);
                }
            } catch (GuzzleException $e) {
                throw new FivexerException('HTTP request failed: ' . $e->getMessage(), 0, $e);
            }

            $headers = $this->responseHeaders($response);
            // fromHeaders returns null exactly when no quota headers are present, so a
            // non-null result always carries data (no separate hasAny() check needed here).
            $quota = QuotaInfo::fromHeaders($headers);
            if ($quota !== null) {
                $this->quota = $quota;
            }
            $status = $response->getStatusCode();

            if ($attempt < $this->maxRetries && $this->isRetryable($status)) {
                $retryAfter = $this->retryAfterSeconds($headers['retry-after'][0] ?? null);
                if ($retryAfter !== null) {
                    \usleep((int) ($retryAfter * 1_000_000));
                }
                $attempt++;
                continue;
            }
            // Non-retryable (or retries exhausted): leave the loop and process the final response.
            break;
        }

        $rawBody = (string) $response->getBody();
        if ($status >= 400) {
            throw $this->toApiException($status, $rawBody, $quota, $headers['retry-after'][0] ?? null);
        }
        if ($status === 204 || $rawBody === '') {
            return null;
        }
        $decoded = \json_decode($rawBody, true);
        return \is_array($decoded) ? $decoded : null;
    }

    /**
     * Build Guzzle options; called once so a retried POST keeps its Idempotency-Key stable.
     *
     * @param array<string, mixed>|null $body
     * @param array<string, string|int|null> $query
     * @return array<string, mixed>
     */
    private function buildOptions(string $method, ?array $body, array $query): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
        ];
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            if ($method === 'POST') {
                // Generate once per options build; this object is reused across retries.
                $headers['Idempotency-Key'] = self::uuidV4();
            }
        }
        $options = ['headers' => $headers];
        if ($body !== null) {
            // JSON_PRESERVE_ZERO_FRACTION keeps 100.0 as 100.0 on the wire, matching the
            // Python (json.dumps) and Java (Gson) SDKs byte-for-byte for float fields.
            //
            // The stdClass swap is not cosmetic: PHP has one array type, so json_encode([])
            // emits `[]`, and a Fastify body schema rejects an array where it wants an object.
            // Endpoints whose body is entirely optional — escalate with no worker, unpark with
            // no flags, applyWeights/revertWeights with no ids — are the only ones that can
            // produce an empty body, and every one of them would 400.
            $options[\GuzzleHttp\RequestOptions::BODY] = \json_encode(
                $body === [] ? new \stdClass() : $body,
                \JSON_PRESERVE_ZERO_FRACTION | \JSON_THROW_ON_ERROR
            );
        }
        $queryClean = [];
        foreach ($query as $key => $value) {
            if ($value !== null) {
                $queryClean[$key] = (string) $value;
            }
        }
        if ($queryClean !== []) {
            $options[\GuzzleHttp\RequestOptions::QUERY] = $queryClean;
        }
        return $options;
    }

    private static function isRetryable(int $status): bool
    {
        return $status === 429 || $status >= 500;
    }

    /** RFC 4122 version-4 UUID (variant bits set), used for Idempotency-Key. */
    private static function uuidV4(): string
    {
        $hex = \bin2hex(\random_bytes(16));
        $hex[12] = '4';
        $hex[16] = \dechex((\hexdec($hex[16]) & 0x3) | 0x8);
        return \vsprintf('%s%s-%s-%s-%s-%s%s%s', \str_split($hex, 4));
    }

    /**
     * @param array<string, list<string>> $headers
     * @return float|null
     */
    private function retryAfterSeconds(?string $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }
        if (!\is_numeric($value)) {
            return null;
        }
        return \max(0.0, (float) $value);
    }

    /** @return array<string, list<string>> */
    private function responseHeaders(ResponseInterface $response): array
    {
        $out = [];
        foreach ($response->getHeaders() as $name => $values) {
            $out[\strtolower($name)] = $values;
        }
        return $out;
    }

    private function toApiException(int $status, string $rawBody, ?QuotaInfo $quota, ?string $retryAfter): FivexerApiException
    {
        $code = 'unknown_error';
        $message = 'http ' . $status;
        $decoded = \json_decode($rawBody, true);
        if (\is_array($decoded) && isset($decoded['error']) && \is_array($decoded['error'])) {
            $err = $decoded['error'];
            if (isset($err['code']) && \is_string($err['code'])) {
                $code = $err['code'];
            }
            if (isset($err['message']) && \is_string($err['message'])) {
                $message = $err['message'];
            }
        }
        return new FivexerApiException(
            statusCode: $status,
            apiCode: $code,
            message: $message,
            quota: $quota,
            retryAfterSeconds: $this->retryAfterSeconds($retryAfter),
        );
    }
}
