package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/**
 * One entry of a bulk feedback submission. Send signals, a reward, or both.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class LearningFeedbackItem {
    private String taskId;
    private Map<String, Double> signals;
    private Double reward;

    public LearningFeedbackItem(String taskId) {
        if (taskId == null) {
            throw new IllegalArgumentException("taskId is required");
        }
        this.taskId = taskId;
    }

    public String getTaskId() { return taskId; }
    public Map<String, Double> getSignals() { return signals; }
    public Double getReward() { return reward; }

    public LearningFeedbackItem signals(Map<String, Double> signals) { this.signals = signals; return this; }
    public LearningFeedbackItem reward(Double reward) { this.reward = reward; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
