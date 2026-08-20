<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\CreateNotificationSequence;
use Fivexer\SDK\Model\NotificationSequence;
use Fivexer\SDK\Model\UpdateNotificationSequence;

/** Sequences describe when to notify, relative to a task lifecycle trigger. */
final class NotificationSequences
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** @return list<NotificationSequence> */
    public function list(): array
    {
        $data = $this->client->request('GET', '/notification-sequences') ?? [];
        /** @var list<NotificationSequence> */
        return Json::parseEach($data, 'sequences', [NotificationSequence::class, 'fromArray']);
    }

    public function create(CreateNotificationSequence $input): NotificationSequence
    {
        return NotificationSequence::fromArray(
            $this->client->request('POST', '/notification-sequences', $input->toArray()) ?? []
        );
    }

    public function get(string $sequenceId): NotificationSequence
    {
        return NotificationSequence::fromArray(
            $this->client->request('GET', '/notification-sequences/' . \rawurlencode($sequenceId)) ?? []
        );
    }

    /** Partial update — fields left unset keep their stored value. */
    public function update(string $sequenceId, UpdateNotificationSequence $update): NotificationSequence
    {
        return NotificationSequence::fromArray(
            $this->client->request(
                'PATCH',
                '/notification-sequences/' . \rawurlencode($sequenceId),
                $update->toArray()
            ) ?? []
        );
    }

    public function remove(string $sequenceId): void
    {
        $this->client->request('DELETE', '/notification-sequences/' . \rawurlencode($sequenceId));
    }
}
