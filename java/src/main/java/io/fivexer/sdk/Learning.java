package io.fivexer.sdk;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.AppliedWeights;
import io.fivexer.sdk.model.LearnedWeightsPreview;
import io.fivexer.sdk.model.LearningFeedbackItem;
import io.fivexer.sdk.model.LearningFeedbackResult;
import io.fivexer.sdk.model.LearningFeedbackResults;
import io.fivexer.sdk.model.LearningStatus;
import io.fivexer.sdk.model.OkResult;
import io.fivexer.sdk.model.WorkerLearningStats;
import java.util.Collections;
import java.util.List;
import java.util.Map;

/**
 * The reinforcement-learning layer: feedback in, re-ranked routing weights out.
 *
 * <p>Learning only re-ranks candidates that are already eligible — hard rules (vetoes,
 * thresholds, CIDR, backlog limits) always apply first and stay deterministic.
 */
public final class Learning {

    private final Fivexer client;

    Learning(Fivexer client) {
        this.client = client;
    }

    public LearningStatus status() {
        return client.request("GET", "/learning/status", null, null, LearningStatus.class, false);
    }

    public WorkerLearningStats workerStats(String workerId) {
        return client.request("GET", "/learning/workers/" + Json.enc(workerId), null, null,
                WorkerLearningStats.class, false);
    }

    /** What applying the learned weights would change, without writing anything. */
    public LearnedWeightsPreview previewWeights(String workerId) {
        return client.request("GET", "/learning/weights/preview", null,
                Json.query("workerId", workerId), LearnedWeightsPreview.class, false);
    }

    public LearnedWeightsPreview previewWeights() {
        return previewWeights(null);
    }

    /** @param workerIds the workers to update, or null for every worker with learned weights */
    public Map<String, Map<String, Double>> applyWeights(List<String> workerIds) {
        JsonObject body = new JsonObject();
        if (workerIds != null) {
            body.add("workerIds", Json.tree(workerIds));
        }
        AppliedWeights applied = client.request("POST", "/learning/weights/apply", body.toString(), null,
                AppliedWeights.class, false);
        return applied.getApplied() == null ? Collections.emptyMap() : applied.getApplied();
    }

    /**
     * Undo the last {@link #applyWeights}, restoring each worker's saved snapshot. Workers who
     * were never synced have nothing to restore and are absent from the result.
     */
    public io.fivexer.sdk.model.RevertedWeights revertWeights(List<String> workerIds) {
        String body = workerIds == null ? "{}" : Json.object("workerIds", workerIds);
        return client.request("POST", "/learning/weights/revert", body, null,
                io.fivexer.sdk.model.RevertedWeights.class, false);
    }

    public io.fivexer.sdk.model.RevertedWeights revertWeights() {
        return revertWeights(null);
    }

    public Map<String, Map<String, Double>> applyWeights() {
        return applyWeights(null);
    }

    /** Report outcome signals against a matched task. */
    public boolean feedback(String taskId, Map<String, Double> signals) {
        OkResult result = client.request("POST", "/tasks/" + Json.enc(taskId) + "/feedback",
                Json.object("signals", signals), null, OkResult.class, false);
        return result.isOk();
    }

    /** Report a direct reward against a matched task. */
    public boolean reward(String taskId, double reward) {
        OkResult result = client.request("POST", "/tasks/" + Json.enc(taskId) + "/reward",
                Json.object("reward", reward), null, OkResult.class, false);
        return result.isOk();
    }

    /** Bulk feedback. Partial success: each result carries its own {@code ok} and error. */
    public List<LearningFeedbackResult> feedbackBulk(List<LearningFeedbackItem> items) {
        JsonArray array = new JsonArray();
        for (LearningFeedbackItem item : items) {
            array.add(Json.tree(item));
        }
        JsonObject body = new JsonObject();
        body.add("items", array);
        LearningFeedbackResults results = client.request("POST", "/learning/feedback", body.toString(),
                null, LearningFeedbackResults.class, false);
        return results.getResults() == null ? Collections.emptyList() : results.getResults();
    }

    /** Discard the learned model and start over. */
    public boolean reset() {
        OkResult result = client.request("POST", "/learning/reset", null, null, OkResult.class, false);
        return result.isOk();
    }
}
