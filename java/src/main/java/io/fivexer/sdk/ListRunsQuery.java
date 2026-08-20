package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.LinkedHashMap;
import java.util.Map;

/** Filters for the workflow-run listings. */
public final class ListRunsQuery {
    private String status;      // active | completed | failed | cancelled
    private String workflowId;
    private String cursor;
    private Integer limit;

    public ListRunsQuery status(String status) { this.status = status; return this; }
    public ListRunsQuery workflowId(String workflowId) { this.workflowId = workflowId; return this; }
    public ListRunsQuery cursor(String cursor) { this.cursor = cursor; return this; }
    public ListRunsQuery limit(Integer limit) { this.limit = limit; return this; }

    public String getStatus() { return status; }
    public String getWorkflowId() { return workflowId; }
    public String getCursor() { return cursor; }
    public Integer getLimit() { return limit; }

    /** A mutable map, so {@code workflows().listRuns} can drop the redundant workflow filter. */
    Map<String, String> toQuery() {
        Map<String, String> built = Json.query("status", status, "workflowId", workflowId,
                "cursor", cursor, "limit", Json.str(limit));
        return built == null ? null : new LinkedHashMap<>(built);
    }
}
