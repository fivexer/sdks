package io.fivexer.sdk.model;

/**
 * The supervisor a session belongs to.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorIdentity {
    private String id;
    private String label;
    private String email;
    private String teamId;

    public String getId() { return id; }
    public String getLabel() { return label; }
    public String getEmail() { return email; }
    public String getTeamId() { return teamId; }
}
