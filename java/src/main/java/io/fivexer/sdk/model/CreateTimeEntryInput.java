package io.fivexer.sdk.model;

import com.google.gson.JsonObject;
import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Input for {@code POST /v1/workers/{id}/time-entries} — a shift that was worked and never
 * logged. Both ends and a reason are required; a closed (signed-off) period refuses it.
 */
public class CreateTimeEntryInput {
    private String startedAt;
    private String endedAt;
    private String note;
    private List<TimeEntryBreak> breaks;
    private String description;
    private String taskId;

    /**
     * @param startedAt ISO-8601
     * @param endedAt ISO-8601
     * @param note why the record is being added — kept on the correction trail
     */
    public CreateTimeEntryInput(String startedAt, String endedAt, String note) {
        if (startedAt == null || endedAt == null || note == null) {
            throw new IllegalArgumentException("startedAt, endedAt and note are required");
        }
        this.startedAt = startedAt;
        this.endedAt = endedAt;
        this.note = note;
    }

    public String getStartedAt() { return startedAt; }
    public String getEndedAt() { return endedAt; }
    public String getNote() { return note; }
    public List<TimeEntryBreak> getBreaks() { return breaks; }
    public String getDescription() { return description; }
    public String getTaskId() { return taskId; }

    /**
     * Breaks taken inside the shift, each with both ends. Needs a worker with a portal login —
     * breaks are filed under it.
     */
    public CreateTimeEntryInput breaks(List<TimeEntryBreak> breaks) { this.breaks = breaks; return this; }

    /** The work note the shift carries. */
    public CreateTimeEntryInput description(String description) { this.description = description; return this; }

    /** The task the time was spent on. */
    public CreateTimeEntryInput taskId(String taskId) { this.taskId = taskId; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        JsonObject o = Json.tree(this).getAsJsonObject();
        TimeEntryBreak.applyExplicitNulls(breaks, o);
        return o.toString();
    }
}
