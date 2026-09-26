package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.SlotBookingResult;
import io.fivexer.sdk.model.SlotCandidate;
import io.fivexer.sdk.model.SlotCandidateList;
import java.util.Collections;
import java.util.List;

/**
 * Reserving a worker's time for a slotted task (one created with a
 * {@link io.fivexer.sdk.model.TimeSlot}). The booking sweep does this automatically ahead of the
 * slot; these are the planner's own calls.
 */
public final class TaskBooking {

    private final Fivexer client;

    TaskBooking(Fivexer client) {
        this.client = client;
    }

    /** Who could take the slot, best first, with what blocks the others. */
    public List<SlotCandidate> candidates(String taskId) {
        SlotCandidateList envelope = client.request("GET",
                "/tasks/" + Json.enc(taskId) + "/booking/candidates", null, null,
                SlotCandidateList.class, false);
        return envelope.getCandidates() == null ? Collections.emptyList() : envelope.getCandidates();
    }

    /** Book the slot for a worker. A clash answers 409 ({@link FivexerApiException}). */
    public SlotBookingResult set(String taskId, String workerId) {
        return set(taskId, workerId, false);
    }

    /**
     * @param force book over a clash or an approved absence. Sent only when true, like
     *     {@link Tasks#assign(String, String, boolean)}.
     */
    public SlotBookingResult set(String taskId, String workerId, boolean force) {
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.addProperty("workerId", workerId);
        if (force) {
            body.addProperty("force", true);
        }
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/booking", body.toString(), null,
                SlotBookingResult.class, false);
    }

    /** Release the reservation; the slot goes back to being unbooked. */
    public void release(String taskId) {
        client.request("DELETE", "/tasks/" + Json.enc(taskId) + "/booking", null, null, Void.class, true);
    }
}
