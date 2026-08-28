package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.PatchWorker;
import io.fivexer.sdk.model.UpsertWorker;
import io.fivexer.sdk.model.WorkerAvailability;
import io.fivexer.sdk.model.WorkerDetail;
import io.fivexer.sdk.model.WorkerList;
import io.fivexer.sdk.model.WorkerMetrics;
import io.fivexer.sdk.model.WorkerQueue;
import io.fivexer.sdk.model.WorkerRef;
import io.fivexer.sdk.model.WorkerTimeEntriesResult;

/** Worker registration, configuration and availability. */
public final class Workers {

    private final Fivexer client;

    Workers(Fivexer client) {
        this.client = client;
    }

    /** Create or replace a worker. Returns the id, which the API generates when not supplied. */
    public String upsert(UpsertWorker worker) {
        UpsertWorker input = worker == null ? new UpsertWorker() : worker;
        WorkerRef ref = client.request("POST", "/workers", input.toJson(), null, WorkerRef.class, false);
        return ref.getId();
    }

    public WorkerList list() {
        return client.request("GET", "/workers", null, null, WorkerList.class, false);
    }

    public WorkerDetail get(String workerId) {
        return client.request("GET", "/workers/" + Json.enc(workerId), null, null, WorkerDetail.class, false);
    }

    /** Partial update — fields left unset keep their stored value. */
    public String patch(String workerId, PatchWorker patch) {
        WorkerRef ref = client.request("PATCH", "/workers/" + Json.enc(workerId), patch.toJson(), null,
                WorkerRef.class, false);
        return ref.getId();
    }

    /**
     * Pause or resume a worker. Pausing preserves the unaccepted backlog ("back in ten minutes").
     */
    public WorkerAvailability setAvailability(String workerId, boolean available) {
        return setAvailability(workerId, available, false);
    }

    /**
     * @param releaseBacklog when pausing, also requeue the unaccepted backlog so others inherit
     *     it now ("gone for the day"). Accepted work in progress is never touched. Only valid
     *     when pausing — the API rejects it on a resume, so it is sent only when true.
     */
    public WorkerAvailability setAvailability(String workerId, boolean available, boolean releaseBacklog) {
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.addProperty("available", available);
        if (releaseBacklog) {
            body.addProperty("releaseBacklog", true);
        }
        return client.request("POST", "/workers/" + Json.enc(workerId) + "/availability", body.toString(),
                null, WorkerAvailability.class, false);
    }

    public WorkerQueue queue(String workerId) {
        return client.request("GET", "/workers/" + Json.enc(workerId) + "/queue", null, null,
                WorkerQueue.class, false);
    }

    /** Recorded time and throughput for one worker over the default window (7 days). */
    public WorkerMetrics metrics(String workerId) {
        return metrics(workerId, null);
    }

    /**
     * The operator-plane mirror of the worker's own portal metrics: today plus a rolling window.
     * Worked time comes from the shift log — shift time minus overlapping breaks — so both planes
     * report the same figure. Requires the control plane.
     *
     * @param window rolling window such as {@code "7d"} (the default) up to {@code "30d"}, or null
     */
    public WorkerMetrics metrics(String workerId, String window) {
        return client.request("GET", "/workers/" + Json.enc(workerId) + "/metrics", null,
                Json.query("window", window), WorkerMetrics.class, false);
    }

    /** The recorded shift and break log for the default window (the last 7 days). */
    public WorkerTimeEntriesResult timeEntries(String workerId) {
        return timeEntries(workerId, null, null);
    }

    /**
     * The recorded shift and break log for a window — the working-time record an EU employer must
     * keep (CJEU C-55/18). A shift is a recorded stretch of availability and breaks are time
     * inside one, so {@code totals.workingMs} is {@code onShiftMs} minus {@code breakMs}.
     * Requires the control plane.
     *
     * @param from ISO-8601 start, or null; @param to ISO-8601 end, or null
     */
    public WorkerTimeEntriesResult timeEntries(String workerId, String from, String to) {
        return client.request("GET", "/workers/" + Json.enc(workerId) + "/time-entries", null,
                Json.query("from", from, "to", to), WorkerTimeEntriesResult.class, false);
    }

    public void remove(String workerId) {
        client.request("DELETE", "/workers/" + Json.enc(workerId), null, null, Void.class, true);
    }
}
