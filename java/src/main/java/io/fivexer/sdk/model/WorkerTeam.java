package io.fivexer.sdk.model;

/** One team membership as it appears on a worker's detail record. */
public class WorkerTeam {

    private String teamId;
    private String key;
    private String name;
    private String color;
    private String role;

    public String getTeamId() { return teamId; }
    public String getKey() { return key; }
    public String getName() { return name; }
    public String getColor() { return color; }

    /** {@code "member"} or {@code "lead"}. */
    public String getRole() { return role; }
}
