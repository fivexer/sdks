"""Worker records: learned weights, team membership, and the two "not simply off shift" states.

The routing-weight fields exist as three separate maps because they answer three separate
questions: what is in force, which entries the learning layer owns, and what to restore on a
revert. Collapsing them would make "an operator vetoed this tag" indistinguishable from "the
model did", which is the difference between a decision to keep and one to undo.
"""

from __future__ import annotations

from fivexer import UpsertWorker
from tests.conftest import json_response, read_body

DETAIL = {
    "id": "agent_1",
    "tags": ["english", "billing"],
    "routingWeights": {"english": 1.0, "billing": 0.0},
    "learnedRoutingWeights": {"billing": 0.0},
    "routingWeightsSnapshot": {"english": 1.0, "billing": 1.0},
    "learnedRoutingWeightsSyncedAt": 1_756_000_000_000,
    "skills": None,
    "teams": [
        {"teamId": "team_1", "key": "support", "name": "Support", "color": "#4488ff", "role": "lead"},
        {"teamId": "team_2", "key": "ops", "name": "Ops", "color": None, "role": "member"},
    ],
    "maxBacklogSize": 5,
    "available": False,
    "invitePending": True,
    "pendingApproval": False,
    "queueDepth": 2,
}


def test_worker_detail_separates_learned_weights_from_the_ones_in_force(server, client):
    server.set_response(json_response(200, DETAIL))

    worker = client.workers.get("agent_1")

    assert worker.routing_weights == {"english": 1.0, "billing": 0.0}
    # The veto on `billing` came from the model, not an operator — that is what makes it
    # revertible, and it is only knowable because the two maps stay separate.
    assert worker.learned_routing_weights == {"billing": 0.0}
    assert worker.routing_weights_snapshot == {"english": 1.0, "billing": 1.0}
    assert worker.learned_routing_weights_synced_at == 1_756_000_000_000


def test_worker_detail_reads_team_membership_with_roles(server, client):
    server.set_response(json_response(200, DETAIL))

    worker = client.workers.get("agent_1")

    assert worker.teams is not None
    assert [(t.team_id, t.role) for t in worker.teams] == [("team_1", "lead"), ("team_2", "member")]
    assert worker.teams[0].name == "Support"
    assert worker.teams[1].color is None


def test_unavailable_is_distinguished_from_invited_and_awaiting_approval(server, client):
    server.set_response(json_response(200, DETAIL))

    worker = client.workers.get("agent_1")

    # An operator UI that renders all three as "paused" tells the wrong story about all three:
    # nobody has to resume an invite, they have to chase it.
    assert worker.available is False
    assert worker.invite_pending is True
    assert worker.pending_approval is False


def test_a_worker_with_no_teams_reads_as_none_rather_than_an_empty_list(server, client):
    server.set_response(json_response(200, {**DETAIL, "teams": None, "invitePending": False}))

    worker = client.workers.get("agent_1")

    # None means "the server said nothing"; [] would claim the worker is in no teams.
    assert worker.teams is None
    assert worker.invite_pending is False


def test_queue_stats_carry_the_same_two_states_per_worker(server, client):
    server.set_response(
        json_response(
            200,
            {
                "plan": "growth",
                "tasks": {"queued": 3},
                "workers": 1,
                "queue": {
                    "oldestWaitingMs": 4200,
                    "perWorker": [
                        {
                            "workerId": "agent_1",
                            "backlog": 2,
                            "maxBacklogSize": 5,
                            "available": False,
                            "invitePending": False,
                            "pendingApproval": True,
                        }
                    ],
                },
            },
        )
    )

    stats = client.stats()

    load = stats.queue.per_worker[0]
    assert load.available is False
    assert load.pending_approval is True
    assert load.invite_pending is False


def test_upsert_can_set_team_membership_and_open_shift_state(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", tags=["english"], team_ids=["team_1", "team_2"], available=True))

    body = read_body(server.last)
    assert body["teamIds"] == ["team_1", "team_2"]
    # The escape hatch for programmatic fleets with no human at a portal: without it a newly
    # created agent worker is off shift and never matched.
    assert body["available"] is True


def test_an_empty_team_list_clears_membership_and_is_not_pruned(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", team_ids=[]))

    # [] is a wholesale replacement with nothing — the only way to remove a worker from every
    # team. Pruning it as "empty" would silently turn a clear into a no-op.
    assert read_body(server.last)["teamIds"] == []


def test_omitting_team_ids_and_availability_leaves_both_untouched(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", tags=["english"]))

    body = read_body(server.last)
    assert "teamIds" not in body
    # Sending available=false here would clock a working person off shift on an unrelated update.
    assert "available" not in body


def test_upsert_can_explicitly_create_a_worker_off_shift(server, client):
    server.set_response(json_response(200, {"id": "agent_1"}))

    client.workers.upsert(UpsertWorker(id="agent_1", available=False))

    assert read_body(server.last)["available"] is False
