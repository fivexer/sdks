package io.fivexer.sdk.model;

import java.util.Map;

/**
 * One entry on a worker's correction trail — what a record said before and after a change, who
 * made it and why.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TimeCorrection {
    private String id;
    private String entryType;
    private String entryId;
    private String workerId;
    private Map<String, Object> before;
    private Map<String, Object> after;
    private String note;
    private String by;
    private String at;

    public String getId() { return id; }

    /** {@code "shift"} or {@code "break"}. */
    public String getEntryType() { return entryType; }

    public String getEntryId() { return entryId; }
    public String getWorkerId() { return workerId; }

    /** What the row held before. Null when this change created the row. */
    public Map<String, Object> getBefore() { return before; }

    public Map<String, Object> getAfter() { return after; }

    /** The stated reason. */
    public String getNote() { return note; }

    /** Who made it, denormalized at write time so the trail survives the author leaving. */
    public String getBy() { return by; }

    /** ISO-8601. */
    public String getAt() { return at; }
}
