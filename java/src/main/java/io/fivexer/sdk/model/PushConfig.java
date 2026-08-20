package io.fivexer.sdk.model;

/**
 * Whether this deployment can send Web Push, and the VAPID key to subscribe with. {@code enabled: false} is a deployment fact, not an error — read it before prompting for notification permission, because a browser only gives you one prompt.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class PushConfig {
    private Boolean enabled;
    private String publicKey;

    public Boolean getEnabled() { return enabled; }
    public String getPublicKey() { return publicKey; }
}
