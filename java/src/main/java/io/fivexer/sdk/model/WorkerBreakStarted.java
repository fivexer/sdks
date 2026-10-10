package io.fivexer.sdk.model;

/**
 * The result of starting a break.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerBreakStarted {
    private String workerId;
    private boolean onBreak;
    private String since;
    private java.util.List<BreakCoverageWarning> coverageWarnings;

    public String getWorkerId() { return workerId; }
    public boolean isOnBreak() { return onBreak; }
    public String getSince() { return since; }

    /** Shifts this break leaves short. Advisory — the break was taken. Empty when none. */
    public java.util.List<BreakCoverageWarning> getCoverageWarnings() {
        return coverageWarnings == null ? java.util.List.of() : coverageWarnings;
    }
}
