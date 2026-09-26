package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Arrays;
import java.util.List;
import java.util.Map;

/**
 * Filters for {@code GET /v1/tasks/history}. Unset filters are omitted from the query string.
 *
 * <p>{@code status} may name several terminal statuses ({@code completed}, {@code cancelled},
 * {@code failed}, {@code expired}); the wire takes them as one comma-separated parameter, and an
 * empty list omits it (all of them).
 */
public final class TaskHistoryQuery {
    private List<String> status;
    private String workerId;
    private String tag;
    private String from;
    private String to;
    private String q;
    private String cursor;
    private Integer limit;

    public TaskHistoryQuery status(List<String> status) { this.status = status; return this; }
    public TaskHistoryQuery status(String... status) { this.status = Arrays.asList(status); return this; }
    public TaskHistoryQuery workerId(String workerId) { this.workerId = workerId; return this; }

    /** One tag the task carried. */
    public TaskHistoryQuery tag(String tag) { this.tag = tag; return this; }

    /** ISO-8601, inclusive, on the finish time. */
    public TaskHistoryQuery from(String from) { this.from = from; return this; }

    /** ISO-8601, exclusive, on the finish time. */
    public TaskHistoryQuery to(String to) { this.to = to; return this; }

    /** Substring of the title or the task id, case-insensitive. Ignored below two characters. */
    public TaskHistoryQuery q(String q) { this.q = q; return this; }

    /** The previous page's {@code nextCursor}. */
    public TaskHistoryQuery cursor(String cursor) { this.cursor = cursor; return this; }

    /** Up to 100; 50 by default. */
    public TaskHistoryQuery limit(Integer limit) { this.limit = limit; return this; }

    public List<String> getStatus() { return status; }
    public String getWorkerId() { return workerId; }
    public String getTag() { return tag; }
    public String getFrom() { return from; }
    public String getTo() { return to; }
    public String getQ() { return q; }
    public String getCursor() { return cursor; }
    public Integer getLimit() { return limit; }

    Map<String, String> toQuery() {
        String joined = (status == null || status.isEmpty()) ? null : String.join(",", status);
        return Json.query("status", joined, "workerId", workerId, "tag", tag, "from", from, "to", to,
                "q", q, "cursor", cursor, "limit", Json.str(limit));
    }
}
