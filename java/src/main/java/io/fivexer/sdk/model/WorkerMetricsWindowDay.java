package io.fivexer.sdk.model;

/**
 * One day's completed count in a rolling metrics window.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMetricsWindowDay {
    private String day;
    private Integer completed;

    public String getDay() { return day; }
    public Integer getCompleted() { return completed; }
}
