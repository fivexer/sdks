package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for updating a worker's location from a mobile device.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class WorkerLocation {
    private Double latitude;
    private Double longitude;

    public WorkerLocation(Double latitude, Double longitude) {
        if (latitude == null) {
            throw new IllegalArgumentException("latitude is required");
        }
        if (longitude == null) {
            throw new IllegalArgumentException("longitude is required");
        }
        this.latitude = latitude;
        this.longitude = longitude;
    }

    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
