<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\CreateJoinLink;
use Fivexer\SDK\Model\CreateJoinLinkResult;
use Fivexer\SDK\Model\JoinLink;

/** QR join links: worker self-registration with preset tags, skills and team. */
final class JoinLinks
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /**
     * Create a link. The `joinUrl` on the result is returned only here — a lost link is
     * re-created, never recovered.
     */
    public function create(CreateJoinLink $input): CreateJoinLinkResult
    {
        return CreateJoinLinkResult::fromArray(
            $this->client->request('POST', '/join-links', $input->toArray()) ?? []
        );
    }

    /** @return list<JoinLink> */
    public function list(): array
    {
        $data = $this->client->request('GET', '/join-links') ?? [];
        /** @var list<JoinLink> */
        return Json::parseEach($data, 'links', [JoinLink::class, 'fromArray']);
    }

    public function revoke(string $linkId): void
    {
        $this->client->request('DELETE', '/join-links/' . \rawurlencode($linkId));
    }
}
