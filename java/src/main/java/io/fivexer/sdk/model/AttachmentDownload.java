package io.fivexer.sdk.model;

/**
 * A short-lived download URL.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AttachmentDownload {
    private String url;
    private long expiresAt;

    public String getUrl() { return url; }
    public long getExpiresAt() { return expiresAt; }
}
