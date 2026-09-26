package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * A finished task, read from the archive — completed, cancelled, failed or expired.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class ArchivedTask {
    private String id;
    private List<String> tags;
    private Double priority;
    private String status;
    private String workerId;
    private Long createdAt;
    private Long matchedAt;
    private long terminalAt;
    private Map<String, Object> meta;
    private String title;
    private Map<String, Object> result;
    private TaskDataSummary data;
    private boolean archived;

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }

    /** {@code completed}, {@code cancelled}, {@code failed} or {@code expired}. */
    public String getStatus() { return status; }

    /** Null when it never reached a worker. */
    public String getWorkerId() { return workerId; }

    public Long getCreatedAt() { return createdAt; }

    /** When it was matched to its worker (epoch ms); null if it never was. */
    public Long getMatchedAt() { return matchedAt; }

    /** When it reached its terminal state (epoch ms). */
    public long getTerminalAt() { return terminalAt; }

    public Map<String, Object> getMeta() { return meta; }
    public String getTitle() { return title; }
    public Map<String, Object> getResult() { return result; }
    public TaskDataSummary getData() { return data; }

    /** Always true — the marker that this came from the archive, not a live store. */
    public boolean isArchived() { return archived; }
}
