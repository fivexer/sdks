package io.fivexer.sdk.model;

/**
 * The signed-in supervisor and their scope. A null {@code teamKey} is a real answer: it means the whole workspace, not a missing value.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorMe {
    private String supervisorId;
    private String label;
    private String email;
    private String workspaceId;
    private String teamKey;

    public String getSupervisorId() { return supervisorId; }
    public String getLabel() { return label; }
    public String getEmail() { return email; }
    public String getWorkspaceId() { return workspaceId; }
    public String getTeamKey() { return teamKey; }
}
