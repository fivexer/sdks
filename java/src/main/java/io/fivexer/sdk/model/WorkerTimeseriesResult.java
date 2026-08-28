package io.fivexer.sdk.model;

import java.util.List;

/**
 * One worker's bucketed history — the per-worker twin of the workspace timeseries.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerTimeseriesResult {
    private String workerId;
    private String from;
    private String to;
    private String bucket;
    private List<WorkerTimeseriesBucket> buckets;

    public String getWorkerId() { return workerId; }
    public String getFrom() { return from; }
    public String getTo() { return to; }
    public String getBucket() { return bucket; }
    public List<WorkerTimeseriesBucket> getBuckets() { return buckets; }
}
