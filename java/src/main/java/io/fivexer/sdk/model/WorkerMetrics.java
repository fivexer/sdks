package io.fivexer.sdk.model;

/**
 * One worker's recorded time and throughput: today, plus a rolling window.
 *
 * <p>The operator-plane mirror of what the worker sees in their own portal — the same numbers on
 * both planes, deliberately, since worked time comes from the one shift log.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMetrics {
    private String workerId;
    private WorkerMetricsDay today;
    private WorkerMetricsPeriod window;

    public String getWorkerId() { return workerId; }
    public WorkerMetricsDay getToday() { return today; }
    public WorkerMetricsPeriod getWindow() { return window; }
}
