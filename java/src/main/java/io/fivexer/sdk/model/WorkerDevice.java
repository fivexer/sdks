package io.fivexer.sdk.model;

/**
 * A registered Expo push token for the native app.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerDevice {
    private String token;
    private String platform;
    private String createdAt;

    public String getToken() { return token; }
    public String getPlatform() { return platform; }
    public String getCreatedAt() { return createdAt; }
}
