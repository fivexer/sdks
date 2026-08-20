package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/** Filters for {@code GET /v1/decisions}. */
public final class ListDecisionsQuery {
    private String taskId;
    private String workerId;
    private Integer limit;

    public ListDecisionsQuery taskId(String taskId) { this.taskId = taskId; return this; }
    public ListDecisionsQuery workerId(String workerId) { this.workerId = workerId; return this; }
    public ListDecisionsQuery limit(Integer limit) { this.limit = limit; return this; }

    public String getTaskId() { return taskId; }
    public String getWorkerId() { return workerId; }
    public Integer getLimit() { return limit; }

    Map<String, String> toQuery() {
        return Json.query("taskId", taskId, "workerId", workerId, "limit", Json.str(limit));
    }
}
