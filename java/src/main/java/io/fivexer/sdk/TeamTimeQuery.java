package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/** Filters for {@code GET /v1/team/time}. Unset filters are omitted; the window defaults to today. */
public final class TeamTimeQuery {
    private String from;
    private String to;
    private String teamId;
    private String workerId;
    private boolean entries;

    /** ISO-8601 start. */
    public TeamTimeQuery from(String from) { this.from = from; return this; }

    /** ISO-8601 end. */
    public TeamTimeQuery to(String to) { this.to = to; return this; }

    /** Narrow the report to one crew. */
    public TeamTimeQuery teamId(String teamId) { this.teamId = teamId; return this; }

    /** Narrow the report to one worker. */
    public TeamTimeQuery workerId(String workerId) { this.workerId = workerId; return this; }

    /**
     * Fetch the raw shift/break log too — needed for a timeline and the day series. Sent only
     * when true.
     */
    public TeamTimeQuery entries(boolean entries) { this.entries = entries; return this; }

    public String getFrom() { return from; }
    public String getTo() { return to; }
    public String getTeamId() { return teamId; }
    public String getWorkerId() { return workerId; }
    public boolean isEntries() { return entries; }

    Map<String, String> toQuery() {
        return Json.query("from", from, "to", to, "teamId", teamId, "workerId", workerId,
                "entries", entries ? "true" : null);
    }
}
