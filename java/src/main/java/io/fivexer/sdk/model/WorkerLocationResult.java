package io.fivexer.sdk.model;

/**
 * A worker's location after a mobile update.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerLocationResult {
    private String workerId;
    private Double latitude;
    private Double longitude;

    public String getWorkerId() { return workerId; }
    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }
}
