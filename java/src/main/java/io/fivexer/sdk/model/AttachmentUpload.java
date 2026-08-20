package io.fivexer.sdk.model;

import java.util.Map;

/**
 * A presigned upload target. Send {@code headers} exactly as given — they are signed.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AttachmentUpload {
    private String url;
    private String method;
    private Map<String, String> headers;
    private long expiresAt;

    public String getUrl() { return url; }
    public String getMethod() { return method; }
    public Map<String, String> getHeaders() { return headers; }
    public long getExpiresAt() { return expiresAt; }
}
