<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\FivexerSupervisor;
use Fivexer\SDK\Model\WorkerCreateAttachment;
use Fivexer\SDK\Resource\WorkerAttachments;

/**
 * Worker-plane attachments and voice ICE.
 *
 * Attachments are how an unattended agent hands over a deliverable as a file rather than a
 * chunked comment thread. The storage core is the workspace plane's — presigned PUT, then a
 * confirm that makes the bytes readable — with two deliberate differences: the uploader comes
 * from the session, so there is no workerId input to spoof, and there is no remove, because a
 * worker who could delete files could erase the evidence of their own work.
 *
 * Voice is **experimental** and not production-ready; the tests below pin only the path and the
 * voice_disabled behaviour so that surface cannot drift silently while it settles.
 */
final class WorkerAttachmentsAndVoiceTest extends ClientTestCase
{
    private const ATTACHMENT = '{"id":"att_1","taskId":"task_1","filename":"report.pdf",'
        . '"contentType":"application/pdf","sizeBytes":11,"status":"pending",'
        . '"uploader":{"type":"worker","id":"agent_1"},"createdAt":1756000000000}';

    private const ICE = '{"iceServers":[{"urls":"stun:stun.test:3478"},'
        . '{"urls":["turn:turn.test:3478","turns:turn.test:5349"],"username":"agent_1",'
        . '"credential":"s3cret"}]}';

    private function supervisor(): FivexerSupervisor
    {
        return new FivexerSupervisor('https://api.fivexer.test', 'sv_s3ss10n', $this->guzzle(), 0);
    }

    // ---- attachments ----

    public function testCreatingPostsToThePortalPathWithTheSessionToken(): void
    {
        $this->enqueueJson(201, '{"attachment":' . self::ATTACHMENT
            . ',"upload":{"url":"https://storage.test/put","method":"PUT","headers":{},"expiresAt":1}}');

        $created = $this->worker()->attachments()->create(
            'task_1',
            new WorkerCreateAttachment('report.pdf', 'application/pdf', 11)
        );

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/portal/tasks/task_1/attachments', $this->pathOf($request));
        self::assertSame('Bearer wt_s3ss10n', $request->getHeaderLine('Authorization'));
        self::assertSame('att_1', $created->attachment->id);
    }

    public function testTheCreateBodyCarriesNoWorkerId(): void
    {
        $this->enqueueJson(201, '{"attachment":' . self::ATTACHMENT
            . ',"upload":{"url":"https://storage.test/put","method":"PUT","headers":{},"expiresAt":1}}');

        $this->worker()->attachments()->create(
            'task_1',
            new WorkerCreateAttachment('report.pdf', 'application/pdf', 11)
        );

        // On this plane the uploader is the session. A workerId field here would be an
        // invitation to attribute a file to someone else, which the separate input type prevents.
        self::assertSame(
            ['filename' => 'report.pdf', 'contentType' => 'application/pdf', 'sizeBytes' => 11],
            $this->requestBodyJson($this->lastRequest())
        );
    }

    public function testConfirmListAndDownloadUseThePortalPaths(): void
    {
        $this->enqueueJson(200, '{"attachment":{"id":"att_1","status":"ready"}}');
        $confirmed = $this->worker()->attachments()->confirm('task_1', 'att_1');
        self::assertSame('/v1/portal/tasks/task_1/attachments/att_1/confirm', $this->pathOf($this->lastRequest()));
        self::assertSame('ready', $confirmed->status);

        $this->enqueueJson(200, '{"attachments":[' . self::ATTACHMENT . ']}');
        $listed = $this->worker()->attachments()->list('task_1');
        self::assertSame('GET', $this->lastRequest()->getMethod());
        self::assertSame('/v1/portal/tasks/task_1/attachments', $this->pathOf($this->lastRequest()));
        self::assertCount(1, $listed);

        $this->enqueueJson(200, '{"url":"https://storage.test/get","expiresAt":1}');
        $download = $this->worker()->attachments()->download('task_1', 'att_1');
        self::assertSame('/v1/portal/tasks/task_1/attachments/att_1/download', $this->pathOf($this->lastRequest()));
        self::assertSame('https://storage.test/get', $download->url);
    }

    public function testAnEmptyAttachmentListReadsAsAnEmptyListNotANull(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->worker()->attachments()->list('task_1'));
    }

    public function testIdsWithSlashesCannotEscapeThePortalPath(): void
    {
        $this->enqueueJson(200, '{"attachments":[]}');

        $this->worker()->attachments()->list('tenant/task');

        self::assertSame('/v1/portal/tasks/tenant%2Ftask/attachments', $this->pathOf($this->lastRequest()));
    }

    /** Queues the three hops of an upload: create, the storage PUT, then confirm. */
    private function enqueueUploadFlow(int $storageStatus): void
    {
        $this->enqueueJson(201, '{"attachment":' . self::ATTACHMENT . ',"upload":{'
            . '"url":"https://storage.test/bucket/att_1?sig=abc","method":"PUT",'
            . '"headers":{"content-type":"application/pdf","x-amz-meta-task":"task_1"},'
            . '"expiresAt":1756000300000}}');
        $this->enqueueEmpty($storageStatus);
        $this->enqueueJson(200, '{"attachment":{"id":"att_1","status":"ready"}}');
    }

    public function testUploadingCreatesPutsTheBytesThenConfirms(): void
    {
        $this->enqueueUploadFlow(200);

        $attachment = $this->worker()->attachments()
            ->upload('task_1', 'hello world', 'report.pdf', 'application/pdf');

        self::assertSame('ready', $attachment->status);
        $requests = $this->recordedRequests();
        self::assertCount(3, $requests);
        self::assertSame('/v1/portal/tasks/task_1/attachments', $this->pathOf($requests[0]));
        // The size is derived from the payload, never trusted from the caller — the server HEADs
        // the object on confirm, so a wrong number here would fail late instead of at create.
        self::assertSame(11, $this->requestBodyJson($requests[0])['sizeBytes']);

        self::assertSame('PUT', $requests[1]->getMethod());
        self::assertSame('storage.test', $requests[1]->getUri()->getHost());
        // The presigned headers are part of the signature; dropping one invalidates it.
        self::assertSame('task_1', $requests[1]->getHeaderLine('x-amz-meta-task'));
        self::assertSame('hello world', $this->requestBody($requests[1]));
        // The session token must not reach a third-party storage host.
        self::assertSame('', $requests[1]->getHeaderLine('Authorization'));

        self::assertSame('/v1/portal/tasks/task_1/attachments/att_1/confirm', $this->pathOf($requests[2]));
    }

    public function testAStorageRejectionSurfacesAsAnUploadFailureAndSkipsConfirmation(): void
    {
        $this->enqueueUploadFlow(403);

        try {
            $this->worker()->attachments()->upload('task_1', 'hello world', 'report.pdf', 'application/pdf');
            self::fail('expected a FivexerApiException');
        } catch (FivexerApiException $error) {
            // A storage rejection is not a /v1 error; one code keeps it diagnosable, and the
            // record stays unconfirmed rather than claiming bytes that never landed.
            self::assertSame('upload_failed', $error->apiCode);
            self::assertSame(403, $error->statusCode);
        }
        // Two hops, not three: confirmation never ran.
        self::assertCount(2, $this->recordedRequests());
    }

    public function testTheWorkerPlaneHasNoAttachmentRemove(): void
    {
        // Files on a task are an operator's to manage and a worker's only to add and read. The
        // absence is the contract, so it is asserted rather than left to be noticed.
        self::assertFalse(\method_exists(WorkerAttachments::class, 'remove'));
    }

    // ---- voice ICE (experimental) ----

    public function testTheWorkerPlaneReadsIceFromThePortalPath(): void
    {
        $this->enqueueJson(200, self::ICE);

        $ice = $this->worker()->voiceIce();

        $request = $this->lastRequest();
        self::assertSame('GET', $request->getMethod());
        self::assertSame('/v1/portal/voice/ice', $this->pathOf($request));
        self::assertCount(2, $ice->iceServers);
    }

    public function testASingleUrlAndAnArrayOfUrlsBothReadAsAList(): void
    {
        $this->enqueueJson(200, self::ICE);

        $servers = $this->worker()->voiceIce()->iceServers;

        // The wire sends either form; normalising to a list means a caller iterating does not
        // have to branch on which one arrived.
        self::assertSame(['stun:stun.test:3478'], $servers[0]->urls);
        self::assertSame(['turn:turn.test:3478', 'turns:turn.test:5349'], $servers[1]->urls);
    }

    public function testTheSessionPlaneReceivesTheTurnCredential(): void
    {
        $this->enqueueJson(200, self::ICE);

        $relay = $this->worker()->voiceIce()->iceServers[1];

        // The console's read view reports credentialSet and withholds the value; a session plane
        // must hand over the real secret or the browser cannot authenticate to the relay.
        self::assertSame('s3cret', $relay->credential);
        self::assertSame('agent_1', $relay->username);
    }

    public function testARelayThatArrivesWithoutUrlsReadsAsNoUrlsRatherThanThrowing(): void
    {
        $this->enqueueJson(200, '{"iceServers":[{"username":"agent_1"},{"urls":null}]}');

        $servers = $this->worker()->voiceIce()->iceServers;

        // Voice is experimental and the server's shape may still move. A malformed relay entry
        // must not take down call setup for the well-formed ones beside it.
        self::assertSame([], $servers[0]->urls);
        self::assertSame([], $servers[1]->urls);
    }

    public function testVoiceDisabledRaisesRatherThanReadingAsNoRelays(): void
    {
        $this->enqueueJson(404, '{"error":{"code":"voice_disabled","message":"voice is off"}}');

        try {
            $this->worker()->voiceIce();
            self::fail('expected a FivexerApiException');
        } catch (FivexerApiException $error) {
            // An empty list would read as "no relay configured, go direct" — a different, and
            // silently broken, outcome from "this workspace has no voice".
            self::assertSame(404, $error->statusCode);
            self::assertSame('voice_disabled', $error->apiCode);
        }
    }

    public function testTheSupervisorPlaneReadsItsOwnIcePath(): void
    {
        $this->enqueueJson(200, self::ICE);

        $ice = $this->supervisor()->voiceIce();

        $request = $this->lastRequest();
        self::assertSame('/v1/supervisor/voice/ice', $this->pathOf($request));
        self::assertSame('Bearer sv_s3ss10n', $request->getHeaderLine('Authorization'));
        self::assertCount(2, $ice->iceServers);
    }

    public function testAnEmptyIceResponseReadsAsAnEmptyList(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->worker()->voiceIce()->iceServers);
    }
}
