package io.fivexer.sdk.model;

import java.util.Map;

/**
 * One evaluated candidate within a {@link Decision}. {@code workerId} is pulled out;
 * every other field (score, eligible, chosen, veto reasons, …) is kept verbatim in
 * {@link #getDetail()} — reason kinds are additive over time, so unknown keys are
 * forward-compatible, not errors.
 */
public class DecisionCandidate {
    private String workerId;
    private Map<String, Object> detail;

    public String getWorkerId() { return workerId; }
    public Map<String, Object> getDetail() { return detail; }

    public void setWorkerId(String workerId) { this.workerId = workerId; }
    public void setDetail(Map<String, Object> detail) { this.detail = detail; }
}
