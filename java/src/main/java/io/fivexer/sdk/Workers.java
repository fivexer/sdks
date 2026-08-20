package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.PatchWorker;
import io.fivexer.sdk.model.UpsertWorker;
import io.fivexer.sdk.model.WorkerAvailability;
import io.fivexer.sdk.model.WorkerDetail;
import io.fivexer.sdk.model.WorkerList;
import io.fivexer.sdk.model.WorkerQueue;
import io.fivexer.sdk.model.WorkerRef;

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

    public void remove(String workerId) {
        client.request("DELETE", "/workers/" + Json.enc(workerId), null, null, Void.class, true);
    }
}
