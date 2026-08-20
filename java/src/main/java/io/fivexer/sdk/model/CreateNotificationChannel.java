package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Input for creating a delivery channel.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class CreateNotificationChannel {
    private String type;
    private String target;
    private List<String> events;
    private String secret;  // write-only: never echoed back on a read

    public CreateNotificationChannel(String type, String target, List<String> events) {
        if (type == null) {
            throw new IllegalArgumentException("type is required");
        }
        if (target == null) {
            throw new IllegalArgumentException("target is required");
        }
        if (events == null) {
            throw new IllegalArgumentException("events is required");
        }
        this.type = type;
        this.target = target;
        this.events = events;
    }

    public String getType() { return type; }
    public String getTarget() { return target; }
    public List<String> getEvents() { return events; }
    public String getSecret() { return secret; }

    public CreateNotificationChannel secret(String secret) { this.secret = secret; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
