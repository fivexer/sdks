package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Worker portal credentials.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class WorkerLogin {
    private String workspaceId;
    private String workerId;
    private String pin;

    public WorkerLogin(String workspaceId, String workerId, String pin) {
        if (workspaceId == null) {
            throw new IllegalArgumentException("workspaceId is required");
        }
        if (workerId == null) {
            throw new IllegalArgumentException("workerId is required");
        }
        if (pin == null) {
            throw new IllegalArgumentException("pin is required");
        }
        this.workspaceId = workspaceId;
        this.workerId = workerId;
        this.pin = pin;
    }

    public String getWorkspaceId() { return workspaceId; }
    public String getWorkerId() { return workerId; }
    public String getPin() { return pin; }



    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
