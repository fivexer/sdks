package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.StartRun;
import io.fivexer.sdk.model.WorkflowDefinition;
import io.fivexer.sdk.model.WorkflowDefinitionInput;
import io.fivexer.sdk.model.WorkflowDefinitionSummary;
import io.fivexer.sdk.model.WorkflowList;
import io.fivexer.sdk.model.WorkflowRun;
import io.fivexer.sdk.model.WorkflowRunPage;
import java.util.Collections;
import java.util.List;
import java.util.Map;

/** Workflow definitions, and starting runs from them. */
public final class Workflows {

    private final Fivexer client;

    Workflows(Fivexer client) {
        this.client = client;
    }

    public List<WorkflowDefinitionSummary> list() {
        WorkflowList envelope = client.request("GET", "/workflows", null, null, WorkflowList.class, false);
        return envelope.getWorkflows() == null ? Collections.emptyList() : envelope.getWorkflows();
    }

    public WorkflowDefinition get(String workflowId) {
        return client.request("GET", "/workflows/" + Json.enc(workflowId), null, null,
                WorkflowDefinition.class, false);
    }

    /**
     * Create or replace a definition. The engine validates the graph on save (unreachable or
     * dangling steps, a missing initial step, a machine step without a handler), so a bad
     * definition comes back as {@code 400 invalid_workflow}.
     */
    public WorkflowDefinition save(String workflowId, WorkflowDefinitionInput input) {
        return client.request("PUT", "/workflows/" + Json.enc(workflowId), input.toJson(), null,
                WorkflowDefinition.class, false);
    }

    public void remove(String workflowId) {
        client.request("DELETE", "/workflows/" + Json.enc(workflowId), null, null, Void.class, true);
    }

    public WorkflowRun run(String workflowId, StartRun input) {
        StartRun start = input == null ? new StartRun() : input;
        return client.request("POST", "/workflows/" + Json.enc(workflowId) + "/runs", start.toJson(),
                null, WorkflowRun.class, false);
    }

    public WorkflowRun run(String workflowId) {
        return run(workflowId, null);
    }

    /** Runs of one definition, newest-first and cursor-paginated. */
    public WorkflowRunPage listRuns(String workflowId, ListRunsQuery query) {
        ListRunsQuery q = query == null ? new ListRunsQuery() : query;
        // The workflow id is already in the path; repeating it as a filter would be noise.
        Map<String, String> filters = q.toQuery();
        if (filters != null) {
            filters.remove("workflowId");
            if (filters.isEmpty()) {
                filters = null;
            }
        }
        return client.request("GET", "/workflows/" + Json.enc(workflowId) + "/runs", null, filters,
                WorkflowRunPage.class, false);
    }

    public WorkflowRunPage listRuns(String workflowId) {
        return listRuns(workflowId, null);
    }
}
