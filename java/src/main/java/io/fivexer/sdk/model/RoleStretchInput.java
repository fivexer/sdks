package io.fivexer.sdk.model;

import com.google.gson.JsonElement;
import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
import java.util.List;

/**
 * A stretch of a shift worked in one role, as sent with {@link CreateTimeEntryInput#roles} or
 * {@link CorrectTimeEntryInput#roles}. {@code positionId} and {@code endedAt} are always sent: a
 * null is meaningful (no particular role; still being worked), never an omitted field.
 */
public class RoleStretchInput {
    private final String positionId;
    private final String startedAt;
    private final String endedAt;

    /**
     * @param positionId a role id on the rate card, or null for no particular role
     * @param startedAt ISO-8601
     * @param endedAt ISO-8601, or null for the stretch still being worked on a running shift
     */
    public RoleStretchInput(String positionId, String startedAt, String endedAt) {
        if (startedAt == null) {
            throw new IllegalArgumentException("startedAt is required");
        }
        this.positionId = positionId;
        this.startedAt = startedAt;
        this.endedAt = endedAt;
    }

    public String getPositionId() { return positionId; }
    public String getStartedAt() { return startedAt; }
    public String getEndedAt() { return endedAt; }

    /** Restore the explicit nulls Gson's omit-nulls rule dropped. */
    static void applyExplicitNulls(List<RoleStretchInput> roles, JsonObject body) {
        if (roles == null || !body.has("roles")) {
            return;
        }
        for (JsonElement element : body.getAsJsonArray("roles")) {
            JsonObject item = element.getAsJsonObject();
            if (!item.has("positionId")) item.add("positionId", JsonNull.INSTANCE);
            if (!item.has("endedAt")) item.add("endedAt", JsonNull.INSTANCE);
        }
    }
}
