package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/** Input for {@code PUT /v1/workers/{id}/links/{connector}}. {@code vendorUserId} is required. */
public class LinkWorkerInput {
    private String vendorUserId;
    private List<String> tags;

    /** @param vendorUserId the worker's id in the connected system */
    public LinkWorkerInput(String vendorUserId) {
        if (vendorUserId == null || vendorUserId.isEmpty()) {
            throw new IllegalArgumentException("vendorUserId is required");
        }
        this.vendorUserId = vendorUserId;
    }

    public String getVendorUserId() { return vendorUserId; }
    public List<String> getTags() { return tags; }

    /** Added to the worker's own tags — a union, never a replacement. */
    public LinkWorkerInput tags(List<String> tags) { this.tags = tags; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
