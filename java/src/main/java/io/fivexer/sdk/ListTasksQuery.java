package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/** Filters for {@code GET /v1/tasks}. Unset filters are omitted from the query string. */
public final class ListTasksQuery {
    private String status;   // queued | pending | accepted | all
    private String cursor;
    private Integer limit;

    public ListTasksQuery status(String status) { this.status = status; return this; }
    public ListTasksQuery cursor(String cursor) { this.cursor = cursor; return this; }
    public ListTasksQuery limit(Integer limit) { this.limit = limit; return this; }

    public String getStatus() { return status; }
    public String getCursor() { return cursor; }
    public Integer getLimit() { return limit; }

    Map<String, String> toQuery() {
        return Json.query("status", status, "cursor", cursor, "limit", Json.str(limit));
    }
}
