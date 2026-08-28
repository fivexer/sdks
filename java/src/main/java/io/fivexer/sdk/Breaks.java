package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.WorkspaceBreakMetrics;

/** Per-worker break rollups for a window. Requires the control plane. */
public final class Breaks {

    private final Fivexer client;

    Breaks(Fivexer client) {
        this.client = client;
    }

    /**
     * With an explicit {@code to}, durations are clipped to the window; without one, a break still
     * running is measured to now.
     *
     * @param from ISO-8601 start, or null; @param to ISO-8601 end, or null (defaults to today)
     */
    public WorkspaceBreakMetrics metrics(String from, String to) {
        return metrics(from, to, null, null);
    }

    /**
     * @param teamId narrow the rollup to one crew, or null for the whole workspace
     * @param workerId narrow it to one worker, or null for everyone
     */
    public WorkspaceBreakMetrics metrics(String from, String to, String teamId, String workerId) {
        return client.request("GET", "/breaks/metrics", null,
                Json.query("from", from, "to", to, "teamId", teamId, "workerId", workerId),
                WorkspaceBreakMetrics.class, false);
    }

    public WorkspaceBreakMetrics metrics() {
        return metrics(null, null, null, null);
    }
}
