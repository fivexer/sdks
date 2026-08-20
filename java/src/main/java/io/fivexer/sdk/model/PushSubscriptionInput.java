package io.fivexer.sdk.model;

import com.google.gson.JsonObject;

/**
 * A browser Web Push subscription, shaped as {@code PushSubscription.toJSON()} returns it.
 *
 * <p>The wire nests the two keys under a {@code keys} object while the browser hands them over
 * flat, so this builds its body by hand rather than reflecting the field names.
 */
public class PushSubscriptionInput {
    private final String endpoint;
    private final String p256dh;
    private final String auth;

    public PushSubscriptionInput(String endpoint, String p256dh, String auth) {
        if (endpoint == null) {
            throw new IllegalArgumentException("endpoint is required");
        }
        if (p256dh == null) {
            throw new IllegalArgumentException("p256dh is required");
        }
        if (auth == null) {
            throw new IllegalArgumentException("auth is required");
        }
        this.endpoint = endpoint;
        this.p256dh = p256dh;
        this.auth = auth;
    }

    public String getEndpoint() { return endpoint; }
    public String getP256dh() { return p256dh; }
    public String getAuth() { return auth; }

    /** Serialise for the wire, nesting the keys the way the API expects. */
    public String toJson() {
        JsonObject keys = new JsonObject();
        keys.addProperty("p256dh", p256dh);
        keys.addProperty("auth", auth);
        JsonObject o = new JsonObject();
        o.addProperty("endpoint", endpoint);
        o.add("keys", keys);
        return o.toString();
    }
}
