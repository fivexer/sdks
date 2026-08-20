package io.fivexer.sdk.model;

import java.util.List;

/**
 * A decision trace from {@code GET /v1/decisions} — who was matched, and why.
 * The full per-candidate score breakdown lives in {@link DecisionCandidate#getDetail()}.
 */
public class Decision {
    private String id;
    private String taskId;
    private String workerId;
    private long matchedAt;     // epoch-ms
    private String mode;
    private List<DecisionCandidate> candidates;

    public String getId() { return id; }
    public String getTaskId() { return taskId; }
    public String getWorkerId() { return workerId; }
    public long getMatchedAt() { return matchedAt; }
    public String getMode() { return mode; }
    public List<DecisionCandidate> getCandidates() { return candidates; }
}
