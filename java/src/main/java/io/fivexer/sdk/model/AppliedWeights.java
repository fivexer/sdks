package io.fivexer.sdk.model;

import java.util.Map;

/**
 * Envelope reporting which weights were written per worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class AppliedWeights {
    private Map<String, Map<String, Double>> applied;

    public Map<String, Map<String, Double>> getApplied() { return applied; }
}
