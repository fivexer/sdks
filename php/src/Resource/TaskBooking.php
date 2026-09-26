<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\SlotBooking;
use Fivexer\SDK\Model\SlotCandidate;

/**
 * The booking on a slotted task — who is reserved for its appointment.
 *
 * A booking reserves a worker's time without putting the task in their backlog or starting a
 * deadline; at the slot's start the task reaches them as an ordinary pending offer. The booking
 * sweep makes most of these on its own; these calls are the planner's override. The calendar
 * view across tasks is {@see Tasks::bookings()}.
 */
final class TaskBooking
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /**
     * Who could take this task's appointment, ranked, with what blocks the others.
     *
     * @return list<SlotCandidate>
     */
    public function candidates(string $taskId): array
    {
        $data = $this->client->request('GET', '/tasks/' . \rawurlencode($taskId) . '/booking/candidates') ?? [];
        /** @var list<SlotCandidate> */
        return Json::parseEach($data, 'candidates', [SlotCandidate::class, 'fromArray']);
    }

    /**
     * Book the appointment for a worker. A clash with another appointment, or an approved
     * absence, comes back as a 409.
     *
     * @param bool $force Book over an approved absence. Sent only when true.
     */
    public function set(string $taskId, string $workerId, bool $force = false): SlotBooking
    {
        $body = ['workerId' => $workerId];
        if ($force) {
            $body['force'] = true;
        }
        $data = $this->client->request('POST', '/tasks/' . \rawurlencode($taskId) . '/booking', $body) ?? [];
        return SlotBooking::fromArray($data['booking'] ?? []);
    }

    /** Release the booking; the appointment goes back to being unbooked. */
    public function release(string $taskId): void
    {
        $this->client->request('DELETE', '/tasks/' . \rawurlencode($taskId) . '/booking');
    }
}
