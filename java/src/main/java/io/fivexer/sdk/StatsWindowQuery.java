package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/** Window for the historical stats endpoints; defaults to the last 24h bucketed hourly. */
public final class StatsWindowQuery {
    private String from;    // ISO-8601
    private String to;      // ISO-8601
    private String bucket;  // hour | day

    public StatsWindowQuery from(String from) { this.from = from; return this; }
    public StatsWindowQuery to(String to) { this.to = to; return this; }
    public StatsWindowQuery bucket(String bucket) { this.bucket = bucket; return this; }

    public String getFrom() { return from; }
    public String getTo() { return to; }
    public String getBucket() { return bucket; }

    Map<String, String> toQuery() {
        return Json.query("from", from, "to", to, "bucket", bucket);
    }
}
