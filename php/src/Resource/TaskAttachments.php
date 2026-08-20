<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\Attachment;
use Fivexer\SDK\Model\AttachmentDownload;
use Fivexer\SDK\Model\CreateAttachment;
use Fivexer\SDK\Model\CreatedAttachment;

/**
 * Files attached to a task.
 *
 * Uploading is a three-step dance — reserve a record, PUT the bytes straight to object storage
 * with the presigned headers, then confirm — which {@see self::upload()} performs for you.
 */
final class TaskAttachments
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** Reserve an attachment record and get a presigned URL to PUT the bytes to. */
    public function create(string $taskId, CreateAttachment $input): CreatedAttachment
    {
        $data = $this->client->request(
            'POST',
            '/tasks/' . \rawurlencode($taskId) . '/attachments',
            $input->toArray()
        ) ?? [];
        return CreatedAttachment::fromArray($data);
    }

    /** Mark an attachment ready once its bytes have landed in object storage. */
    public function confirm(string $taskId, string $attachmentId): Attachment
    {
        $data = $this->client->request(
            'POST',
            '/tasks/' . \rawurlencode($taskId) . '/attachments/' . \rawurlencode($attachmentId) . '/confirm'
        ) ?? [];
        return Attachment::fromArray($data['attachment'] ?? []);
    }

    /** @return list<Attachment> */
    public function list(string $taskId): array
    {
        $data = $this->client->request('GET', '/tasks/' . \rawurlencode($taskId) . '/attachments') ?? [];
        /** @var list<Attachment> */
        return Json::parseEach($data, 'attachments', [Attachment::class, 'fromArray']);
    }

    public function download(string $taskId, string $attachmentId): AttachmentDownload
    {
        $data = $this->client->request(
            'GET',
            '/tasks/' . \rawurlencode($taskId) . '/attachments/' . \rawurlencode($attachmentId) . '/download'
        ) ?? [];
        return AttachmentDownload::fromArray($data);
    }

    public function remove(string $taskId, string $attachmentId): void
    {
        $this->client->request(
            'DELETE',
            '/tasks/' . \rawurlencode($taskId) . '/attachments/' . \rawurlencode($attachmentId)
        );
    }

    /**
     * Create, PUT the bytes to object storage, then confirm — in one call.
     *
     * The size is derived from $payload, and the presigned headers are sent verbatim because
     * they are part of the signature. A non-2xx from storage throws a FivexerApiException with
     * code `upload_failed`, leaving the record unconfirmed rather than claiming bytes that
     * never landed.
     *
     * @param string|null $workerId Attribute the upload to a worker (API-key callers only)
     */
    public function upload(
        string $taskId,
        string $payload,
        string $filename,
        string $contentType,
        ?string $workerId = null,
    ): Attachment {
        $input = new CreateAttachment($filename, $contentType, \strlen($payload));
        if ($workerId !== null) {
            $input->workerId($workerId);
        }
        $created = $this->create($taskId, $input);
        $this->client->putBytes($created->upload, $payload);
        return $this->confirm($taskId, $created->attachment->id);
    }
}
