package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.WorkspaceBreakMetrics;

/** Per-worker break rollups for a window. Requires the control plane. */
public final class Breaks {

    private final Fivexer client;

    Breaks(Fivexer client) {
        this.client = client;
    }

    /** @param from ISO-8601 start, or null; @param to ISO-8601 end, or null (defaults to today) */
    public WorkspaceBreakMetrics metrics(String from, String to) {
        return client.request("GET", "/breaks/metrics", null, Json.query("from", from, "to", to),
                WorkspaceBreakMetrics.class, false);
    }

    public WorkspaceBreakMetrics metrics() {
        return metrics(null, null);
    }
}
