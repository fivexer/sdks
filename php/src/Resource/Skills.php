<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\PatchSkill;
use Fivexer\SDK\Model\Skill;
use Fivexer\SDK\Model\UpsertSkill;

/** The workspace skill catalogue. */
final class Skills
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    public function create(UpsertSkill $input): Skill
    {
        return Skill::fromArray($this->client->request('POST', '/skills', $input->toArray()) ?? []);
    }

    /**
     * @param string|null $q Free-text filter over key and name
     * @return list<Skill>
     */
    public function list(?string $q = null, ?int $limit = null): array
    {
        $data = $this->client->request('GET', '/skills', null, ['q' => $q, 'limit' => $limit]) ?? [];
        /** @var list<Skill> */
        return Json::parseEach($data, 'skills', [Skill::class, 'fromArray']);
    }

    public function get(string $skillId): Skill
    {
        return Skill::fromArray($this->client->request('GET', '/skills/' . \rawurlencode($skillId)) ?? []);
    }

    /** Partial update — the key is immutable. */
    public function patch(string $skillId, PatchSkill $patch): Skill
    {
        return Skill::fromArray(
            $this->client->request('PATCH', '/skills/' . \rawurlencode($skillId), $patch->toArray()) ?? []
        );
    }

    public function remove(string $skillId): void
    {
        $this->client->request('DELETE', '/skills/' . \rawurlencode($skillId));
    }

    /**
     * Skills commonly held alongside the ones already chosen. The list travels as one
     * comma-joined query parameter; an empty selection omits it entirely.
     *
     * @param list<string>|null $selected
     * @return list<Skill>
     */
    public function suggest(?array $selected = null, ?int $limit = null): array
    {
        $joined = ($selected === null || $selected === []) ? null : \implode(',', $selected);
        $data = $this->client->request('GET', '/skills/suggest', null, [
            'selected' => $joined,
            'limit' => $limit,
        ]) ?? [];
        /** @var list<Skill> */
        return Json::parseEach($data, 'skills', [Skill::class, 'fromArray']);
    }
}
