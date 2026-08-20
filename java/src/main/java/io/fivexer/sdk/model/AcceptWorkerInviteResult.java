package io.fivexer.sdk.model;

/**
 * What a worker gets back after consuming an emailed invite. Unlike {@link JoinWorkspaceResult} the worker id already existed — an operator created the identity when they sent the invite.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AcceptWorkerInviteResult {
    private String token;
    private String expiresAt;
    private String workspaceId;
    private String workerId;
    private String label;
    private String portalUrl;

    public String getToken() { return token; }
    public String getExpiresAt() { return expiresAt; }
    public String getWorkspaceId() { return workspaceId; }
    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public String getPortalUrl() { return portalUrl; }
}
