<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\CreateWorkerIdentity;
use Fivexer\SDK\Model\InviteWorkerIdentity;
use Fivexer\SDK\Model\PublicWorkerIdentity;
use Fivexer\SDK\Model\UpdateWorkerIdentity;
use Fivexer\SDK\Model\WorkerInviteResult;

/**
 * Worker portal credentials.
 *
 * A worker (a routing target) and an identity (a way to sign in) are separate things:
 * {@see self::invite()} creates both when the worker does not exist yet.
 */
final class Identities
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** @return list<PublicWorkerIdentity> */
    public function list(): array
    {
        $data = $this->client->request('GET', '/worker-identities') ?? [];
        /** @var list<PublicWorkerIdentity> */
        return Json::parseEach($data, 'identities', [PublicWorkerIdentity::class, 'fromArray']);
    }

    /**
     * Email a set-your-PIN link. Check `emailStatus` on the result: `mailer_unconfigured` means
     * the deployment cannot send mail, and `inviteUrl` is then the only way to deliver it.
     */
    public function invite(InviteWorkerIdentity $input): WorkerInviteResult
    {
        return WorkerInviteResult::fromArray(
            $this->client->request('POST', '/worker-identities/invite', $input->toArray()) ?? []
        );
    }

    public function resendInvite(string $workerId): WorkerInviteResult
    {
        return WorkerInviteResult::fromArray(
            $this->client->request(
                'POST',
                '/worker-identities/' . \rawurlencode($workerId) . '/invite/resend'
            ) ?? []
        );
    }

    /** Set a PIN directly, for a worker who will never receive email (a kiosk, an agent). */
    public function create(string $workerId, CreateWorkerIdentity $input): PublicWorkerIdentity
    {
        $data = $this->client->request(
            'POST',
            '/workers/' . \rawurlencode($workerId) . '/identity',
            $input->toArray()
        ) ?? [];
        return PublicWorkerIdentity::fromArray($data['identity'] ?? []);
    }

    public function update(string $workerId, UpdateWorkerIdentity $patch): PublicWorkerIdentity
    {
        $data = $this->client->request(
            'PATCH',
            '/workers/' . \rawurlencode($workerId) . '/identity',
            $patch->toArray()
        ) ?? [];
        return PublicWorkerIdentity::fromArray($data['identity'] ?? []);
    }

    /** Removes the sign-in, not the worker — they stay routable. */
    public function remove(string $workerId): void
    {
        $this->client->request('DELETE', '/workers/' . \rawurlencode($workerId) . '/identity');
    }
}
