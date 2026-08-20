package io.fivexer.sdk.model;

/**
 * A redeemed supervisor session. {@code expiresAt} is epoch-<em>milliseconds</em>, not the ISO-8601 string the worker plane sends — the two planes genuinely differ on the wire, and this mirrors the server rather than papering over it.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorSession {
    private String token;
    private Long expiresAt;
    private SupervisorIdentity supervisor;
    private String workspaceId;

    public String getToken() { return token; }
    public Long getExpiresAt() { return expiresAt; }
    public SupervisorIdentity getSupervisor() { return supervisor; }
    public String getWorkspaceId() { return workspaceId; }
}
