package io.fivexer.sdk.model;

import java.util.List;

/**
 * What applying the learned weights would change.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class LearnedWeightsPreview {
    private List<PreviewedWorkerWeights> workers;

    public List<PreviewedWorkerWeights> getWorkers() { return workers; }
}
