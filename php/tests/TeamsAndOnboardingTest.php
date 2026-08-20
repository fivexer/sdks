<?php

declare(strict_types=1);

namespace Fivexer\SDK\Tests;

use Fivexer\SDK\Model\CreateJoinLink;
use Fivexer\SDK\Model\CreateWorkerIdentity;
use Fivexer\SDK\Model\InviteWorkerIdentity;
use Fivexer\SDK\Model\PatchTeam;
use Fivexer\SDK\Model\UpdateWorkerIdentity;
use Fivexer\SDK\Model\UpsertTeam;
use Fivexer\SDK\Model\WorkerSkillAssignment;

/**
 * Teams, QR join links and worker identities — the roster-building surface.
 *
 * These share one hazard the rest of the API does not: several return a URL that is
 * credential-equivalent until consumed. The tests pin the wire shape and the fact those URLs
 * survive parsing, because a dropped inviteUrl leaves an operator with no way to onboard on a
 * deployment whose mailer is unconfigured — the common case, not the edge case.
 */
final class TeamsAndOnboardingTest extends ClientTestCase
{
    private const TEAM = '{"id":"team_1","key":"billing","tag":"team:billing","name":"Billing",'
        . '"description":"Invoices","color":"#5b8def","createdAt":"2026-08-01T09:00:00.000Z"}';

    private const IDENTITY = '{"id":"wid_1","workerId":"agent_1","label":"Ada",'
        . '"email":"ada@example.com","status":"active","hasPin":false,"activatedAt":null,'
        . '"createdAt":"2026-08-01T09:00:00.000Z","revokedAt":null}';

    // ---- teams ----

    public function testCreatingATeamSendsKeyAndNameAndOmitsUnsetFields(): void
    {
        $this->enqueueJson(201, self::TEAM);

        $team = $this->client()->teams()->create(new UpsertTeam('billing', 'Billing'));

        $request = $this->lastRequest();
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/teams', $this->pathOf($request));
        self::assertSame(['key' => 'billing', 'name' => 'Billing'], $this->requestBodyJson($request));
        // The derived tag is what matching sees, so parsing it is load-bearing.
        self::assertSame('team:billing', $team->tag);
    }

    public function testPatchingATeamOmitsAbsentFieldsSoStoredValuesSurvive(): void
    {
        $this->enqueueJson(200, self::TEAM);

        $this->client()->teams()->patch('team_1', (new PatchTeam())->name('Billing EU'));

        $request = $this->lastRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame(['name' => 'Billing EU'], $this->requestBodyJson($request));
    }

    public function testListingTeamsUnwrapsTheEnvelope(): void
    {
        $this->enqueueJson(200, '{"teams":[' . self::TEAM . ']}');

        $teams = $this->client()->teams()->list();

        self::assertCount(1, $teams);
        self::assertSame('team_1', $teams[0]->id);
    }

    public function testAnAbsentTeamsArrayReadsAsEmpty(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->client()->teams()->list());
    }

    public function testTeamMembersCarryTheirRoleAndJoinTime(): void
    {
        $this->enqueueJson(200, '{"teamId":"team_1","members":['
            . '{"workerId":"agent_1","role":"lead","addedAt":"2026-08-02T10:00:00Z"}]}');

        $members = $this->client()->teams()->members('team_1');

        self::assertSame('team_1', $members->teamId);
        self::assertSame('lead', $members->members[0]->role);
    }

    public function testSettingMembersReplacesTheRosterWholesale(): void
    {
        // PUT, not PATCH — a worker absent from the list is removed, and the verb is the only
        // warning a caller gets.
        $this->enqueueJson(200, '{"teamId":"team_1","workerIds":["agent_1"]}');

        $roster = $this->client()->teams()->setMembers('team_1', ['agent_1']);

        $request = $this->lastRequest();
        self::assertSame('PUT', $request->getMethod());
        self::assertSame('/v1/teams/team_1/members', $this->pathOf($request));
        self::assertSame(['workerIds' => ['agent_1']], $this->requestBodyJson($request));
        self::assertSame(['agent_1'], $roster->workerIds);
    }

    public function testRemovingATeamToleratesAnEmpty204(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->teams()->remove('team_1');

        self::assertSame('DELETE', $this->lastRequest()->getMethod());
    }

    public function testGettingATeamEncodesTheIdSegment(): void
    {
        $this->enqueueJson(200, self::TEAM);

        $this->client()->teams()->get('team/../admin');

        self::assertSame('/v1/teams/team%2F..%2Fadmin', $this->pathOf($this->lastRequest()));
    }

    // ---- join links ----

    public function testCreatingAJoinLinkReturnsTheUrlOnlyOnce(): void
    {
        $this->enqueueJson(201, '{"link":{"id":"jl_1","label":"Warehouse","teamId":"team_1",'
            . '"tags":["warehouse"],"requiresApproval":true,"maxUses":25,"useCount":0,'
            . '"status":"active","createdAt":"x","expiresAt":"y","revokedAt":null},'
            . '"joinUrl":"https://5xer.com/join/abc"}');

        $result = $this->client()->joinLinks()->create(
            (new CreateJoinLink('Warehouse'))
                ->teamId('team_1')
                ->tags(['warehouse'])
                ->skills([new WorkerSkillAssignment('sk_1', 3)])
                ->requiresApproval(true)
                ->maxUses(25)
        );

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertSame('Warehouse', $body['label']);
        self::assertSame('sk_1', $body['skills'][0]['skillId']);
        self::assertTrue($body['requiresApproval']);
        // A lost joinUrl is re-created, never recovered — losing it in parsing is unrecoverable.
        self::assertSame('https://5xer.com/join/abc', $result->joinUrl);
        self::assertSame(25, $result->link->maxUses);
    }

    public function testAJoinLinkWithNoCapParsesMaxUsesAsNullNotZero(): void
    {
        // null means unlimited; 0 would mean exhausted. Collapsing them inverts the meaning.
        $this->enqueueJson(200, '{"links":[{"id":"jl_1","label":"Open","teamId":null,"tags":[],'
            . '"requiresApproval":false,"maxUses":null,"useCount":7,"status":"active",'
            . '"createdAt":"x","expiresAt":"y","revokedAt":null}]}');

        $links = $this->client()->joinLinks()->list();

        self::assertNull($links[0]->maxUses);
        self::assertSame(7, $links[0]->useCount);
    }

    public function testRevokingAJoinLinkIsADelete(): void
    {
        $this->enqueueEmpty(204);

        $this->client()->joinLinks()->revoke('jl_1');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/join-links/jl_1', $this->pathOf($request));
    }

    public function testAnAbsentLinksArrayReadsAsEmpty(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->client()->joinLinks()->list());
    }

    // ---- worker identities ----

    public function testInvitingAWorkerReportsWhetherTheMailActuallyWentOut(): void
    {
        // mailer_unconfigured is the common deployment state, and the only signal telling an
        // operator they must hand the link over themselves.
        $this->enqueueJson(201, '{"identity":' . self::IDENTITY . ',"workerCreated":true,'
            . '"emailStatus":"mailer_unconfigured","inviteUrl":"https://5xer.com/i?token=t0k"}');

        $result = $this->client()->identities()->invite(
            (new InviteWorkerIdentity('ada@example.com'))->label('Ada')
        );

        $request = $this->lastRequest();
        self::assertSame('/v1/worker-identities/invite', $this->pathOf($request));
        self::assertSame(['email' => 'ada@example.com', 'label' => 'Ada'], $this->requestBodyJson($request));
        self::assertSame('mailer_unconfigured', $result->emailStatus);
        self::assertStringEndsWith('token=t0k', $result->inviteUrl);
        self::assertTrue($result->workerCreated);
    }

    public function testResendingAnInviteNeedsNoBody(): void
    {
        $this->enqueueJson(200, '{"identity":' . self::IDENTITY . ',"workerCreated":false,'
            . '"emailStatus":"sent","inviteUrl":"https://x/i"}');

        $result = $this->client()->identities()->resendInvite('agent_1');

        self::assertSame('/v1/worker-identities/agent_1/invite/resend', $this->pathOf($this->lastRequest()));
        self::assertFalse($result->workerCreated);
    }

    public function testListingIdentitiesNeverExposesAPinOnlyWhetherOneIsSet(): void
    {
        $this->enqueueJson(200, '{"identities":[' . self::IDENTITY . ']}');

        $identities = $this->client()->identities()->list();

        self::assertFalse($identities[0]->hasPin);
    }

    public function testAnAbsentIdentitiesArrayReadsAsEmpty(): void
    {
        $this->enqueueJson(200, '{}');

        self::assertSame([], $this->client()->identities()->list());
    }

    public function testCreatingAnIdentityDirectlyUnwrapsTheEnvelope(): void
    {
        $this->enqueueJson(201, '{"identity":{"id":"wid_1","workerId":"agent_1","label":"Ada",'
            . '"status":"active","hasPin":true}}');

        $identity = $this->client()->identities()->create('agent_1', new CreateWorkerIdentity('Ada', '4821'));

        $request = $this->lastRequest();
        self::assertSame('/v1/workers/agent_1/identity', $this->pathOf($request));
        self::assertSame(['label' => 'Ada', 'pin' => '4821'], $this->requestBodyJson($request));
        // The caller gets the identity itself, not a one-field wrapper around it.
        self::assertTrue($identity->hasPin);
    }

    public function testAnUnsetEmailIsAbsentFromAnIdentityPatch(): void
    {
        $this->enqueueJson(200, '{"identity":' . self::IDENTITY . '}');

        $this->client()->identities()->update('agent_1', (new UpdateWorkerIdentity())->label('Ada L.'));

        self::assertSame(['label' => 'Ada L.'], $this->requestBodyJson($this->lastRequest()));
    }

    public function testClearingAnIdentityEmailSendsAnExplicitNull(): void
    {
        // Absent means "leave it"; null means "erase it". compact() drops nulls, so this is the
        // one input model that cannot go through the shared serialiser.
        $this->enqueueJson(200, '{"identity":' . self::IDENTITY . '}');

        $this->client()->identities()->update('agent_1', (new UpdateWorkerIdentity())->clearEmail());

        $body = $this->requestBodyJson($this->lastRequest());
        self::assertArrayHasKey('email', $body);
        self::assertNull($body['email']);
    }

    public function testSettingAnEmailAfterClearingItWins(): void
    {
        // The flag must not outlive the decision that set it, or a later email() would silently
        // still erase the address.
        $this->enqueueJson(200, '{"identity":' . self::IDENTITY . '}');

        $this->client()->identities()->update(
            'agent_1',
            (new UpdateWorkerIdentity())->clearEmail()->email('new@example.com')
        );

        self::assertSame('new@example.com', $this->requestBodyJson($this->lastRequest())['email']);
    }

    public function testRevokingAnIdentityIsAStatusPatchNotADelete(): void
    {
        $this->enqueueJson(200, '{"identity":' . self::IDENTITY . '}');

        $this->client()->identities()->update(
            'agent_1',
            (new UpdateWorkerIdentity())->status('revoked')->pin('9137')
        );

        $request = $this->lastRequest();
        self::assertSame('PATCH', $request->getMethod());
        self::assertSame(['pin' => '9137', 'status' => 'revoked'], $this->requestBodyJson($request));
    }

    public function testRemovingAnIdentityLeavesTheWorkerItselfAlone(): void
    {
        // DELETE /workers/{id}/identity, not DELETE /workers/{id} — the worker stays routable,
        // they just lose the ability to sign in.
        $this->enqueueEmpty(204);

        $this->client()->identities()->remove('agent_1');

        $request = $this->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/workers/agent_1/identity', $this->pathOf($request));
    }
}
