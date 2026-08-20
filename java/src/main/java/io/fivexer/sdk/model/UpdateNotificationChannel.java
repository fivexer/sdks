package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Partial channel update — absent fields keep their stored value.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class UpdateNotificationChannel {
    private String type;
    private String target;
    private List<String> events;
    private String secret;
    private Boolean disabled;

    public UpdateNotificationChannel() {}

    public String getType() { return type; }
    public String getTarget() { return target; }
    public List<String> getEvents() { return events; }
    public String getSecret() { return secret; }
    public Boolean getDisabled() { return disabled; }

    public UpdateNotificationChannel type(String type) { this.type = type; return this; }
    public UpdateNotificationChannel target(String target) { this.target = target; return this; }
    public UpdateNotificationChannel events(List<String> events) { this.events = events; return this; }
    public UpdateNotificationChannel secret(String secret) { this.secret = secret; return this; }
    public UpdateNotificationChannel disabled(Boolean disabled) { this.disabled = disabled; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
