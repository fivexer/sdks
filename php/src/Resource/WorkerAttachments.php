<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\FivexerWorker;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\Attachment;
use Fivexer\SDK\Model\AttachmentDownload;
use Fivexer\SDK\Model\CreatedAttachment;
use Fivexer\SDK\Model\WorkerCreateAttachment;

/**
 * Files on the worker's own tasks — how an unattended agent hands over a deliverable as a file
 * instead of a chunked comment thread.
 *
 * The same storage core as {@see TaskAttachments}: bytes go straight to object storage via a
 * presigned PUT, and {@see self::confirm()} is what makes them readable. Two differences, both
 * deliberate. The uploader is derived from the session, so {@see WorkerCreateAttachment} has no
 * `workerId` to spoof. And there is no `remove` — files on a task are an operator's to manage
 * and a worker's only to add and read; a delete here would let a worker erase the evidence of
 * their own work.
 *
 * Deployments without object storage answer 501 `storage_unavailable`.
 */
final class WorkerAttachments
{
    public function __construct(private readonly FivexerWorker $client)
    {
    }

    /** Reserve an attachment record and get a presigned URL to PUT the bytes to. */
    public function create(string $taskId, WorkerCreateAttachment $input): CreatedAttachment
    {
        $data = $this->client->portalRequest(
            'POST',
            '/portal/tasks/' . \rawurlencode($taskId) . '/attachments',
            $input->toArray()
        ) ?? [];
        return CreatedAttachment::fromArray($data);
    }

    /** Confirm the bytes landed — the server HEADs the object as the authoritative size check. */
    public function confirm(string $taskId, string $attachmentId): Attachment
    {
        $data = $this->client->portalRequest(
            'POST',
            '/portal/tasks/' . \rawurlencode($taskId)
                . '/attachments/' . \rawurlencode($attachmentId) . '/confirm'
        ) ?? [];
        return Attachment::fromArray($data['attachment'] ?? []);
    }

    /** @return list<Attachment> */
    public function list(string $taskId): array
    {
        $data = $this->client->portalRequest(
            'GET',
            '/portal/tasks/' . \rawurlencode($taskId) . '/attachments'
        ) ?? [];
        /** @var list<Attachment> */
        return Json::parseEach($data, 'attachments', [Attachment::class, 'fromArray']);
    }

    /** A short-lived presigned download URL for a confirmed attachment. */
    public function download(string $taskId, string $attachmentId): AttachmentDownload
    {
        $data = $this->client->portalRequest(
            'GET',
            '/portal/tasks/' . \rawurlencode($taskId)
                . '/attachments/' . \rawurlencode($attachmentId) . '/download'
        ) ?? [];
        return AttachmentDownload::fromArray($data);
    }

    /**
     * Create, PUT the bytes to object storage, then confirm — in one call.
     *
     * The size is derived from $payload, and the presigned headers are sent verbatim because
     * they are part of the signature. A non-2xx from storage throws a FivexerApiException with
     * code `upload_failed`, leaving the record unconfirmed rather than claiming bytes that never
     * landed.
     */
    public function upload(string $taskId, string $payload, string $filename, string $contentType): Attachment
    {
        $created = $this->create(
            $taskId,
            new WorkerCreateAttachment($filename, $contentType, \strlen($payload))
        );
        $this->client->putBytes($created->upload, $payload);
        return $this->confirm($taskId, $created->attachment->id);
    }
}
