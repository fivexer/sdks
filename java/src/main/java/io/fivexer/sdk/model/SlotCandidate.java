package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * Who could take a slotted task's time, and — for the ones who could not — what blocks them.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SlotCandidate {
    private String workerId;
    private double score;
    private double effectivePriority;
    private boolean bookable;
    private List<Map<String, Object>> reasons;
    private String clashingTaskId;
    private List<SlotConflict> blocked;
    private List<SlotConflict> warnings;

    public String getWorkerId() { return workerId; }

    /** Match score, as the readiness check reports it. */
    public double getScore() { return score; }

    /** Base priority plus score and any geo/learning boost — what ranks the candidates. */
    public double getEffectivePriority() { return effectivePriority; }

    /** Whether a booking for this worker would go through right now. */
    public boolean isBookable() { return bookable; }

    /**
     * Why they are or are not eligible, in the same vocabulary the decision traces use — a skill
     * gate, a veto, a prior hand-back.
     */
    public List<Map<String, Object>> getReasons() { return reasons; }

    /** An appointment already on them that overlaps this one, or null. */
    public String getClashingTaskId() { return clashingTaskId; }

    /** Approved absences covering the slot. These refuse a booking. Null when none. */
    public List<SlotConflict> getBlocked() { return blocked; }

    /** Soft conflicts. These allow a booking and are recorded on it. Null when none. */
    public List<SlotConflict> getWarnings() { return warnings; }
}
