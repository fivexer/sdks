package io.fivexer.sdk.model;

/**
 * One time bucket of historical throughput. Null latencies mean no data, not zero.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class StatsTimeseriesBucket {
    private String bucketStart;
    private int completed;
    private int cancelled;
    private Double avgWaitMs;
    private Double p50WaitMs;
    private Double p95WaitMs;
    private Double avgHandleMs;

    public String getBucketStart() { return bucketStart; }
    public int getCompleted() { return completed; }
    public int getCancelled() { return cancelled; }
    public Double getAvgWaitMs() { return avgWaitMs; }
    public Double getP50WaitMs() { return p50WaitMs; }
    public Double getP95WaitMs() { return p95WaitMs; }
    public Double getAvgHandleMs() { return avgHandleMs; }
}
