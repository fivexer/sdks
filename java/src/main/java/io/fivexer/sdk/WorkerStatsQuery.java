package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/**
 * Window plus an optional crew filter for the per-worker productivity report.
 *
 * <p>Separate from {@link StatsWindowQuery} because {@code teamId} narrows a per-worker report
 * and has no meaning on the workspace timeseries.
 */
public final class WorkerStatsQuery {
    private String from;    // ISO-8601
    private String to;      // ISO-8601
    private String bucket;  // hour | day
    private String teamId;

    public WorkerStatsQuery from(String from) { this.from = from; return this; }
    public WorkerStatsQuery to(String to) { this.to = to; return this; }
    public WorkerStatsQuery bucket(String bucket) { this.bucket = bucket; return this; }
    public WorkerStatsQuery teamId(String teamId) { this.teamId = teamId; return this; }

    public String getFrom() { return from; }
    public String getTo() { return to; }
    public String getBucket() { return bucket; }
    public String getTeamId() { return teamId; }

    Map<String, String> toQuery() {
        return Json.query("from", from, "to", to, "bucket", bucket, "teamId", teamId);
    }
}
