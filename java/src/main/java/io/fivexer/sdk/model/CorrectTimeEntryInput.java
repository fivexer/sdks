package io.fivexer.sdk.model;

import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Input for {@code PATCH /v1/workers/{id}/time-entries/{entryId}} — a correction to one recorded
 * shift or break, keeping what it said before.
 *
 * <p>The reason ({@code note}) is mandatory. Patch semantics: a field never set is omitted and
 * left alone; {@link #reopen()}, {@link #clearDescription()} and {@link #clearTaskId()} send an
 * explicit {@code null}, which a null argument cannot express.
 */
public class CorrectTimeEntryInput {
    private String startedAt;
    private String endedAt;
    private String note;
    private String type;
    private List<TimeEntryBreak> breaks;
    private String description;
    private String taskId;

    private transient boolean reopen;
    private transient boolean clearDescription;
    private transient boolean clearTaskId;

    /** @param note why the record is being changed — kept on the correction trail */
    public CorrectTimeEntryInput(String note) {
        if (note == null || note.isEmpty()) {
            throw new IllegalArgumentException("note is required");
        }
        this.note = note;
    }

    public String getStartedAt() { return startedAt; }
    public String getEndedAt() { return endedAt; }
    public String getNote() { return note; }
    public String getType() { return type; }
    public List<TimeEntryBreak> getBreaks() { return breaks; }
    public String getDescription() { return description; }
    public String getTaskId() { return taskId; }

    /** ISO-8601. */
    public CorrectTimeEntryInput startedAt(String startedAt) { this.startedAt = startedAt; return this; }

    /** ISO-8601. */
    public CorrectTimeEntryInput endedAt(String endedAt) { this.endedAt = endedAt; this.reopen = false; return this; }

    /**
     * Send {@code "endedAt": null} — reopen the record, for a shift closed by mistake while
     * somebody kept working.
     */
    public CorrectTimeEntryInput reopen() { this.endedAt = null; this.reopen = true; return this; }

    /** {@code "shift"} or {@code "break"} — saves a lookup when the caller already knows. */
    public CorrectTimeEntryInput type(String type) { this.type = type; return this; }

    /**
     * Shifts only: every break the shift holds afterwards, judged and saved with it in one
     * transaction. A break with an id is that break, moved or left alone; without one it is new;
     * a break inside the shift that is not listed is removed. Leave unset to keep the breaks.
     */
    public CorrectTimeEntryInput breaks(List<TimeEntryBreak> breaks) { this.breaks = breaks; return this; }

    /** Shifts only: the work note. */
    public CorrectTimeEntryInput description(String description) {
        this.description = description;
        this.clearDescription = false;
        return this;
    }

    /** Send {@code "description": null}. */
    public CorrectTimeEntryInput clearDescription() {
        this.description = null;
        this.clearDescription = true;
        return this;
    }

    /** Shifts only: the task the time was spent on. */
    public CorrectTimeEntryInput taskId(String taskId) { this.taskId = taskId; this.clearTaskId = false; return this; }

    /** Send {@code "taskId": null}. */
    public CorrectTimeEntryInput clearTaskId() { this.taskId = null; this.clearTaskId = true; return this; }

    /** Serialise the set fields, then write the requested clears as explicit nulls. */
    public String toJson() {
        JsonObject o = Json.tree(this).getAsJsonObject();
        if (reopen) o.add("endedAt", JsonNull.INSTANCE);
        if (clearDescription) o.add("description", JsonNull.INSTANCE);
        if (clearTaskId) o.add("taskId", JsonNull.INSTANCE);
        TimeEntryBreak.applyExplicitNulls(breaks, o);
        return o.toString();
    }
}
