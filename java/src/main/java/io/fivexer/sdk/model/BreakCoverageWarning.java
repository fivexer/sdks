package io.fivexer.sdk.model;

/**
 * A shift a break would leave short. Advisory: the break is still allowed.
 *
 * <p>Populated by Gson via field reflection and exposed read-only.
 */
public class BreakCoverageWarning {
    private String shiftInstanceId;
    private String shiftName;
    private int remaining;
    private int required;
    private String kind;

    public String getShiftInstanceId() { return shiftInstanceId; }
    public String getShiftName() { return shiftName; }
    public int getRemaining() { return remaining; }
    public int getRequired() { return required; }
    /** {@code headcount} or {@code qualification}. */
    public String getKind() { return kind; }
}
