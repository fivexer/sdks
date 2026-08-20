package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * An Expo push token from the native app. Browsers use {@link PushSubscriptionInput} instead.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class WorkerDeviceInput {
    private String token;
    private String platform;

    public WorkerDeviceInput(String token) {
        if (token == null) {
            throw new IllegalArgumentException("token is required");
        }
        this.token = token;
    }

    public String getToken() { return token; }
    public String getPlatform() { return platform; }

    public WorkerDeviceInput platform(String platform) { this.platform = platform; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
