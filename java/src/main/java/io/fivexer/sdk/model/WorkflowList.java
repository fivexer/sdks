package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for {@code GET /v1/workflows}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowList {
    private List<WorkflowDefinitionSummary> workflows;

    public List<WorkflowDefinitionSummary> getWorkflows() { return workflows; }
}
