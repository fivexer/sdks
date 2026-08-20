package io.fivexer.sdk.model;

import java.util.List;

/**
 * Historical throughput over a window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class StatsTimeseriesResult {
    private String from;
    private String to;
    private String bucket;
    private List<StatsTimeseriesBucket> buckets;

    public String getFrom() { return from; }
    public String getTo() { return to; }
    public String getBucket() { return bucket; }
    public List<StatsTimeseriesBucket> getBuckets() { return buckets; }
}
