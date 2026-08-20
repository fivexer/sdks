package io.fivexer.sdk.model;

/**
 * A registered browser Web Push subscription.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class PushSubscription {
    private String endpoint;
    private String createdAt;

    public String getEndpoint() { return endpoint; }
    public String getCreatedAt() { return createdAt; }
}
