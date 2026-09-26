package io.fivexer.sdk.model;

import com.google.gson.JsonArray;
import com.google.gson.JsonElement;
import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
import java.util.List;

/**
 * A break inside a shift, as sent with {@link CreateTimeEntryInput#breaks} or
 * {@link CorrectTimeEntryInput#breaks}.
 *
 * <p>{@code endedAt} is always sent: on a correction a null end is an explicit {@code null} (the
 * break is still open), never an omitted field. {@code id} only means something on a correction,
 * where it names the existing break being moved or kept; leave it unset for a new break.
 */
public class TimeEntryBreak {
    private String id;
    private String startedAt;
    private String endedAt;

    /**
     * @param startedAt ISO-8601
     * @param endedAt ISO-8601, or null for a break still open (corrections only)
     */
    public TimeEntryBreak(String startedAt, String endedAt) {
        if (startedAt == null) {
            throw new IllegalArgumentException("startedAt is required");
        }
        this.startedAt = startedAt;
        this.endedAt = endedAt;
    }

    public String getId() { return id; }
    public String getStartedAt() { return startedAt; }
    public String getEndedAt() { return endedAt; }

    /** The existing break this entry is — corrections only. */
    public TimeEntryBreak id(String id) { this.id = id; return this; }

    /** Restore the explicit {@code endedAt: null} Gson's omit-nulls rule dropped. */
    static void applyExplicitNulls(List<TimeEntryBreak> breaks, JsonObject body) {
        if (breaks == null || !body.has("breaks")) {
            return;
        }
        JsonArray out = body.getAsJsonArray("breaks");
        for (JsonElement element : out) {
            JsonObject item = element.getAsJsonObject();
            if (!item.has("endedAt")) {
                item.add("endedAt", JsonNull.INSTANCE);
            }
        }
    }
}
