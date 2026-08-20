package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for bulk feedback.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class LearningFeedbackResults {
    private List<LearningFeedbackResult> results;

    public List<LearningFeedbackResult> getResults() { return results; }
}
