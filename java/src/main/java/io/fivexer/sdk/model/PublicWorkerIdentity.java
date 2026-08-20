package io.fivexer.sdk.model;

/**
 * A worker's portal credential. Never carries the PIN — only {@code hasPin}, whether one is set.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class PublicWorkerIdentity {
    private String id;
    private String workerId;
    private String label;
    private String status;
    private String email;
    private Boolean hasPin;
    private String activatedAt;
    private String createdAt;
    private String revokedAt;

    public String getId() { return id; }
    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public String getStatus() { return status; }
    public String getEmail() { return email; }
    public Boolean getHasPin() { return hasPin; }
    public String getActivatedAt() { return activatedAt; }
    public String getCreatedAt() { return createdAt; }
    public String getRevokedAt() { return revokedAt; }
}
