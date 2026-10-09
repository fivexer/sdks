package io.fivexer.sdk.model;

import java.util.Map;

/**
 * {@code GET /v1/tasks/{id}/outcome} — where a task stands and, once finished, its result.
 *
 * <p>Populated by Gson via field reflection and exposed read-only.
 */
public class TaskOutcome {
    private String id;
    private String status;
    private boolean done;
    private String workerId;
    private Map<String, Object> result;
    private Long terminalAt;

    public String getId() { return id; }

    /** queued, pending, accepted, … while live; completed, failed, cancelled or expired once done. */
    public String getStatus() { return status; }

    public boolean isDone() { return done; }

    /** Who holds or held it; null while queued. */
    public String getWorkerId() { return workerId; }

    public Map<String, Object> getResult() { return result; }

    /** Epoch ms; null while live. */
    public Long getTerminalAt() { return terminalAt; }
}
