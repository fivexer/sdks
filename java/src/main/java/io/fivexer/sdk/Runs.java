package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.WorkflowRun;
import io.fivexer.sdk.model.WorkflowRunPage;
import io.fivexer.sdk.model.WorkflowRunSteps;
import java.util.Map;

/** Workflow runs across the workspace, including the external (callback) step outcomes. */
public final class Runs {

    private final Fivexer client;

    Runs(Fivexer client) {
        this.client = client;
    }

    public WorkflowRunPage list(ListRunsQuery query) {
        ListRunsQuery q = query == null ? new ListRunsQuery() : query;
        return client.request("GET", "/workflow-runs", null, q.toQuery(), WorkflowRunPage.class, false);
    }

    public WorkflowRunPage list() {
        return list(null);
    }

    public WorkflowRun get(String runId) {
        return client.request("GET", "/workflow-runs/" + Json.enc(runId), null, null,
                WorkflowRun.class, false);
    }

    /** The per-step state view — what a canvas UI paints. */
    public WorkflowRunSteps steps(String runId) {
        return client.request("GET", "/workflow-runs/" + Json.enc(runId) + "/steps", null, null,
                WorkflowRunSteps.class, false);
    }

    public WorkflowRun cancel(String runId) {
        return client.request("POST", "/workflow-runs/" + Json.enc(runId) + "/cancel", null, null,
                WorkflowRun.class, false);
    }

    /** Complete an external (callback) step, advancing the run. */
    public WorkflowRun completeStep(String runId, String stepId, Map<String, Object> data) {
        String body = data == null ? "{}" : Json.object("data", data);
        return client.request("POST",
                "/workflow-runs/" + Json.enc(runId) + "/steps/" + Json.enc(stepId) + "/complete",
                body, null, WorkflowRun.class, false);
    }

    public WorkflowRun completeStep(String runId, String stepId) {
        return completeStep(runId, stepId, null);
    }

    /** Fail an external (callback) step. */
    public WorkflowRun failStep(String runId, String stepId, String error) {
        String body = error == null ? "{}" : Json.object("error", error);
        return client.request("POST",
                "/workflow-runs/" + Json.enc(runId) + "/steps/" + Json.enc(stepId) + "/fail",
                body, null, WorkflowRun.class, false);
    }

    public WorkflowRun failStep(String runId, String stepId) {
        return failStep(runId, stepId, null);
    }
}
