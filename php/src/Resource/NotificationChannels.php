<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\CreateNotificationChannel;
use Fivexer\SDK\Model\NotificationChannel;
use Fivexer\SDK\Model\UpdateNotificationChannel;

/** Channels describe where to deliver. The signing secret is write-only and never returned. */
final class NotificationChannels
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** @return list<NotificationChannel> */
    public function list(): array
    {
        $data = $this->client->request('GET', '/notification-channels') ?? [];
        /** @var list<NotificationChannel> */
        return Json::parseEach($data, 'channels', [NotificationChannel::class, 'fromArray']);
    }

    public function create(CreateNotificationChannel $input): NotificationChannel
    {
        return NotificationChannel::fromArray(
            $this->client->request('POST', '/notification-channels', $input->toArray()) ?? []
        );
    }

    public function get(string $channelId): NotificationChannel
    {
        return NotificationChannel::fromArray(
            $this->client->request('GET', '/notification-channels/' . \rawurlencode($channelId)) ?? []
        );
    }

    /** Partial update — fields left unset keep their stored value. */
    public function update(string $channelId, UpdateNotificationChannel $update): NotificationChannel
    {
        return NotificationChannel::fromArray(
            $this->client->request(
                'PATCH',
                '/notification-channels/' . \rawurlencode($channelId),
                $update->toArray()
            ) ?? []
        );
    }

    public function remove(string $channelId): void
    {
        $this->client->request('DELETE', '/notification-channels/' . \rawurlencode($channelId));
    }
}
