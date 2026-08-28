"""The reinforcement-learning layer: feedback in, re-ranked routing weights out."""

from __future__ import annotations

from fivexer import LearningFeedbackItem
from tests.conftest import json_response, query_pairs, read_body


def test_reading_learning_status_reports_whether_it_is_shadowing(server, client):
    # Shadow mode means the model scores but never influences routing — an important
    # distinction when interpreting the numbers.
    server.set_response(
        json_response(
            200,
            {
                "enabled": True,
                "shadowMode": True,
                "autoWeights": False,
                "signalWeights": {"csat": 1.0},
                "rewards": {"accept": 1, "complete": 2},
                "stats": {"decisions": 1200, "rewards": 800, "totalReward": 940.5, "averageReward": 1.175},
                "modelSize": 64,
            },
        )
    )

    status = client.learning.status()

    assert status.enabled is True
    assert status.shadow_mode is True
    assert status.stats is not None
    assert status.stats.average_reward == 1.175


def test_per_worker_stats_separate_learned_weights_from_configured_ones(server, client):
    server.set_response(
        json_response(
            200,
            {
                "workerId": "agent_1",
                "skills": [
                    {
                        "tag": "english",
                        "count": 40,
                        "meanReward": 1.4,
                        "currentWeight": 100,
                        "learnedWeight": 118,
                        "skill": None,
                    },
                    {
                        "tag": "skill:skl_1",
                        "count": 12,
                        "meanReward": 0.2,
                        "currentWeight": None,
                        "learnedWeight": 55,
                        "skill": {"id": "skl_1", "name": "Refunds"},
                    },
                ],
            },
        )
    )

    stats = client.learning.worker_stats("agent_1")

    assert server.last.url.path == "/v1/learning/workers/agent_1"
    assert stats.skills[0].current_weight == 100
    assert stats.skills[0].learned_weight == 118
    # A tag with no configured weight is still learnable.
    assert stats.skills[1].current_weight is None
    assert stats.skills[1].skill == {"id": "skl_1", "name": "Refunds"}


def test_previewing_weights_for_one_worker_scopes_the_request(server, client):
    server.set_response(
        json_response(
            200,
            {"workers": [{"workerId": "agent_1", "current": {"english": 100}, "learned": {"english": 118}}]},
        )
    )

    preview = client.learning.preview_weights("agent_1")

    assert query_pairs(server.last) == {"workerId": "agent_1"}
    assert preview.workers[0].learned == {"english": 118}


def test_previewing_weights_for_everyone_sends_no_scope(server, client):
    server.set_response(json_response(200, {"workers": []}))

    assert client.learning.preview_weights().workers == []
    assert query_pairs(server.last) == {}


def test_applying_learned_weights_reports_what_changed_per_worker(server, client):
    server.set_response(json_response(200, {"applied": {"agent_1": {"english": 118}}}))

    applied = client.learning.apply_weights(["agent_1"])

    assert read_body(server.last) == {"workerIds": ["agent_1"]}
    assert applied == {"agent_1": {"english": 118}}


def test_applying_weights_to_a_workspace_with_nothing_learned_yet_returns_empty(server, client):
    server.set_response(json_response(200, {"applied": {}}))

    assert client.learning.apply_weights() == {}


def test_reporting_outcome_signals_against_a_task(server, client):
    server.set_response(json_response(200, {"ok": True}))

    assert client.learning.feedback("task_8fk2", {"csat": 0.9, "handleTime": -0.2}) is True
    assert server.last.url.path == "/v1/tasks/task_8fk2/feedback"
    assert read_body(server.last) == {"signals": {"csat": 0.9, "handleTime": -0.2}}


def test_reporting_a_direct_reward_against_a_task(server, client):
    server.set_response(json_response(200, {"ok": True}))

    assert client.learning.reward("task_8fk2", 1.5) is True
    assert read_body(server.last) == {"reward": 1.5}


def test_bulk_feedback_reports_failures_per_item_rather_than_failing_the_batch(server, client):
    server.set_response(
        json_response(
            200,
            {
                "results": [
                    {"taskId": "task_8fk2", "ok": True},
                    {"taskId": "missing", "ok": False, "error": "no decision recorded for task"},
                ]
            },
        )
    )

    results = client.learning.feedback_bulk(
        [
            LearningFeedbackItem(task_id="task_8fk2", reward=1),
            LearningFeedbackItem(task_id="missing", signals={"csat": 1}),
        ]
    )

    assert [(r.task_id, r.ok) for r in results] == [("task_8fk2", True), ("missing", False)]
    assert results[1].error == "no decision recorded for task"
    assert results[0].error is None


def test_resetting_discards_the_learned_model(server, client):
    server.set_response(json_response(200, {"ok": True}))

    assert client.learning.reset() is True
    assert server.last.url.path == "/v1/learning/reset"


def test_a_refused_reset_is_reported_as_not_ok(server, client):
    server.set_response(json_response(200, {"ok": False}))

    assert client.learning.reset() is False


def test_preview_weights_carries_the_two_widening_flags(server, client):
    server.set_response(json_response(200, {"workers": []}))

    client.learning.preview_weights("agent_1", override_manual=True, include_unexplored_tags=True)

    assert query_pairs(server.last) == {
        "workerId": "agent_1",
        "overrideManual": "true",
        "includeUnexploredTags": "true",
    }


def test_preview_weights_omits_flags_that_were_not_asked_for(server, client):
    server.set_response(json_response(200, {"workers": []}))

    client.learning.preview_weights("agent_1")

    # Both default off server-side. Echoing `false` would make "I did not ask" look like a
    # deliberate refusal, and the preview would stop matching what a bare apply() writes.
    assert query_pairs(server.last) == {"workerId": "agent_1"}


def test_preview_can_send_a_flag_off_explicitly(server, client):
    server.set_response(json_response(200, {"workers": []}))

    client.learning.preview_weights(override_manual=False)

    assert query_pairs(server.last) == {"overrideManual": "false"}


def test_apply_weights_sends_the_flags_in_the_body(server, client):
    server.set_response(json_response(200, {"applied": {"agent_1": {"billing": 0.0}}}))

    applied = client.learning.apply_weights(["agent_1"], override_manual=True, include_unexplored_tags=False)

    assert read_body(server.last) == {
        "workerIds": ["agent_1"],
        "overrideManual": True,
        "includeUnexploredTags": False,
    }
    assert applied == {"agent_1": {"billing": 0.0}}


def test_apply_weights_without_flags_sends_only_the_worker_ids(server, client):
    server.set_response(json_response(200, {"applied": {}}))

    client.learning.apply_weights(["agent_1"])

    assert read_body(server.last) == {"workerIds": ["agent_1"]}
