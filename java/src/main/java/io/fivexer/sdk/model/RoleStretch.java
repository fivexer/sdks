package io.fivexer.sdk.model;

/**
 * A stretch of a recorded shift spent in one role, with the role's name.
 *
 * <p>Populated by Gson via field reflection and exposed read-only.
 */
public class RoleStretch {
    private String positionId;
    private String startedAt;
    private String endedAt;
    private String label;

    /** A role id on the rate card; null is time in no particular role. */
    public String getPositionId() { return positionId; }
    public String getStartedAt() { return startedAt; }
    /** Null only for the stretch still being worked on a running shift. */
    public String getEndedAt() { return endedAt; }
    /** The role's name; null for time in no particular role. */
    public String getLabel() { return label; }
}
