<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;

/** Notification sequences (when to notify) and channels (where to deliver). */
final class Notifications
{
    private readonly NotificationSequences $sequencesResource;
    private readonly NotificationChannels $channelsResource;

    public function __construct(Fivexer $client)
    {
        $this->sequencesResource = new NotificationSequences($client);
        $this->channelsResource = new NotificationChannels($client);
    }

    public function sequences(): NotificationSequences
    {
        return $this->sequencesResource;
    }

    public function channels(): NotificationChannels
    {
        return $this->channelsResource;
    }
}
