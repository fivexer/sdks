package io.fivexer.sdk.model;

/**
 * What a worker gets back after self-registering through a QR join link. {@code workerId} is server-generated — show it to them, it is their PIN-login username.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class JoinWorkspaceResult {
    private String token;
    private String expiresAt;
    private String workspaceId;
    private String workerId;
    private String label;
    private Boolean pendingApproval;
    private String portalUrl;

    public String getToken() { return token; }
    public String getExpiresAt() { return expiresAt; }
    public String getWorkspaceId() { return workspaceId; }
    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public Boolean getPendingApproval() { return pendingApproval; }
    public String getPortalUrl() { return portalUrl; }
}
