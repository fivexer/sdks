package io.fivexer.sdk.model;

/**
 * A shift or break as it reads after a correction.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class CorrectedTimeEntry {
    private String id;
    private String type;
    private String startedAt;
    private String endedAt;
    private boolean corrected;
    private String source;
    private String endReason;
    private String reason;
    private String description;
    private String taskId;

    public String getId() { return id; }

    /** {@code "shift"} or {@code "break"}. */
    public String getType() { return type; }

    public String getStartedAt() { return startedAt; }

    /** Null when the record is open. */
    public String getEndedAt() { return endedAt; }

    /** Always true. */
    public boolean isCorrected() { return corrected; }

    public String getSource() { return source; }
    public String getEndReason() { return endReason; }
    public String getReason() { return reason; }

    /** Shifts: the note the record carries after the change. */
    public String getDescription() { return description; }

    /** Shifts: the task the record carries after the change. */
    public String getTaskId() { return taskId; }
}
