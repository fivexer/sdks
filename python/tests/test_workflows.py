"""Workflow definitions and the runs started from them."""

from __future__ import annotations

import pytest

from fivexer import (
    FivexerApiError,
    ListRunsQuery,
    StartRun,
    WorkflowDefinitionInput,
    WorkflowRouting,
    WorkflowStep,
)
from tests.conftest import empty_response, json_response, query_pairs, read_body

RUN_BODY = {
    "id": "run_1",
    "workflowId": "wf_onboard",
    "status": "active",
    "currentStepId": "collect",
    "currentTaskId": "task_8fk2",
    "initiatorWorkerId": "agent_1",
    "context": {"customerId": "c_1"},
    "definitionVersion": 2,
    "history": [],
    "parallelBranches": None,
    "createdAt": 1750000000000,
    "updatedAt": 1750000000000,
}

DEFINITION_BODY = {
    "id": "wf_onboard",
    "name": "Onboarding",
    "version": 2,
    "initialStepId": "collect",
    "defaultTimeoutMs": 600000,
    "steps": [
        {
            "id": "collect",
            "name": "Collect docs",
            "taskType": "assignment",
            "assignmentTemplate": {"tags": ["english"]},
            "defaultNextStepId": "review",
        },
        {
            "id": "review",
            "name": "Review",
            "taskType": "external",
            "external": {"name": "compliance"},
            "timeoutMs": 300000,
            "defaultNextStepId": None,
        },
    ],
}


# ---- definitions ----------------------------------------------------------


def test_listing_definitions_returns_id_and_name_only(server, client):
    server.set_response(json_response(200, {"workflows": [{"id": "wf_onboard", "name": "Onboarding"}]}))

    workflows = client.workflows.list()

    assert [(w.id, w.name) for w in workflows] == [("wf_onboard", "Onboarding")]


def test_reading_a_definition_returns_its_full_graph(server, client):
    server.set_response(json_response(200, DEFINITION_BODY))

    definition = client.workflows.get("wf_onboard")

    assert definition.version == 2
    assert definition.initial_step_id == "collect"
    assert [s.id for s in definition.steps] == ["collect", "review"]
    assert definition.steps[1].end_workflow is True


def test_saving_a_definition_puts_it_at_the_url_that_names_it(server, client):
    server.set_response(json_response(200, DEFINITION_BODY))

    client.workflows.save(
        "wf_onboard",
        WorkflowDefinitionInput(
            name="Onboarding",
            initial_step_id="collect",
            steps=[
                WorkflowStep(
                    id="collect",
                    name="Collect docs",
                    task_type="assignment",
                    routing=[WorkflowRouting(condition="result.ok", target_step_id="review")],
                )
            ],
        ),
    )

    assert server.last.method == "PUT"
    assert server.last.url.path == "/v1/workflows/wf_onboard"
    body = read_body(server.last)
    assert "id" not in body
    assert body["steps"][0]["routing"] == [{"condition": "result.ok", "targetStepId": "review"}]


def test_saving_an_unreachable_graph_is_rejected_by_the_engine(server, client):
    server.set_response(
        json_response(400, {"error": {"code": "invalid_workflow", "message": "initial step 'missing' is not defined"}})
    )

    with pytest.raises(FivexerApiError) as excinfo:
        client.workflows.save("wf_broken", WorkflowDefinitionInput(name="Broken", steps=[]))

    assert excinfo.value.code == "invalid_workflow"
    assert excinfo.value.status_code == 400


def test_deleting_a_definition_returns_nothing(server, client):
    server.set_response(empty_response(204))

    assert client.workflows.remove("wf_onboard") is None


# ---- starting and listing runs -------------------------------------------


def test_starting_a_run_returns_the_first_active_step(server, client):
    server.set_response(json_response(201, RUN_BODY))

    run = client.workflows.run("wf_onboard", StartRun(context={"customerId": "c_1"}, initiator_worker_id="agent_1"))

    assert server.last.url.path == "/v1/workflows/wf_onboard/runs"
    assert (run.status, run.current_step_id, run.current_task_id) == ("active", "collect", "task_8fk2")
    assert run.context == {"customerId": "c_1"}


def test_starting_a_run_with_no_input_still_posts_a_body(server, client):
    server.set_response(json_response(201, RUN_BODY))

    client.workflows.run("wf_onboard")

    assert read_body(server.last) == {}


def test_listing_one_definitions_runs_filters_by_status(server, client):
    server.set_response(json_response(200, {"runs": [RUN_BODY], "nextCursor": None}))

    page = client.workflows.list_runs("wf_onboard", ListRunsQuery(status="active", limit=25))

    assert server.last.url.path == "/v1/workflows/wf_onboard/runs"
    assert query_pairs(server.last) == {"status": "active", "limit": "25"}
    assert page.next_cursor is None
    assert [r.id for r in page.runs] == ["run_1"]


def test_listing_runs_across_the_workspace_can_filter_by_definition(server, client):
    server.set_response(json_response(200, {"runs": [], "nextCursor": "cursor_r1"}))

    page = client.runs.list(ListRunsQuery(workflow_id="wf_onboard", status="completed"))

    assert server.last.url.path == "/v1/workflow-runs"
    assert query_pairs(server.last) == {"workflowId": "wf_onboard", "status": "completed"}
    assert page.next_cursor == "cursor_r1"


def test_reading_a_run_returns_the_steps_it_has_completed(server, client):
    server.set_response(
        json_response(
            200,
            {
                **RUN_BODY,
                "status": "completed",
                "currentStepId": None,
                "history": [
                    {
                        "stepId": "collect",
                        "taskId": "task_8fk2",
                        "workerId": "agent_1",
                        "completedAt": 1750000500000,
                        "result": {"ok": True},
                    }
                ],
            },
        )
    )

    run = client.runs.get("run_1")

    assert run.status == "completed"
    assert run.history[0].worker_id == "agent_1"


def test_the_step_view_reports_which_step_is_waiting_on_a_callback(server, client):
    server.set_response(
        json_response(
            200,
            {
                "runId": "run_1",
                "status": "active",
                "steps": [
                    {
                        "stepId": "collect",
                        "name": "Collect docs",
                        "taskType": "assignment",
                        "state": "completed",
                        "taskId": "task_8fk2",
                        "workerId": "agent_1",
                        "completedAt": 1,
                        "result": {"ok": True},
                    },
                    {
                        "stepId": "review",
                        "name": "Review",
                        "taskType": "external",
                        "state": "awaiting_callback",
                        "taskId": None,
                        "workerId": None,
                        "completedAt": None,
                        "result": None,
                    },
                ],
            },
        )
    )

    view = client.runs.steps("run_1")

    assert view.run_id == "run_1"
    assert [(s.step_id, s.state) for s in view.steps] == [
        ("collect", "completed"),
        ("review", "awaiting_callback"),
    ]
    assert view.steps[1].task_id is None


def test_cancelling_a_run_stops_it(server, client):
    server.set_response(json_response(200, {**RUN_BODY, "status": "cancelled", "currentStepId": None}))

    run = client.runs.cancel("run_1")

    assert server.last.method == "POST"
    assert server.last.url.path == "/v1/workflow-runs/run_1/cancel"
    assert run.status == "cancelled"


# ---- external (callback) steps -------------------------------------------


def test_completing_a_callback_step_advances_the_run(server, client):
    server.set_response(json_response(200, {**RUN_BODY, "currentStepId": "notify"}))

    run = client.runs.complete_step("run_1", "review", {"approved": True})

    assert server.last.url.path == "/v1/workflow-runs/run_1/steps/review/complete"
    assert read_body(server.last) == {"data": {"approved": True}}
    assert run.current_step_id == "notify"


def test_failing_a_callback_step_fails_the_run(server, client):
    server.set_response(json_response(200, {**RUN_BODY, "status": "failed"}))

    run = client.runs.fail_step("run_1", "review", "compliance system unreachable")

    assert server.last.url.path == "/v1/workflow-runs/run_1/steps/review/fail"
    assert read_body(server.last) == {"error": "compliance system unreachable"}
    assert run.status == "failed"
