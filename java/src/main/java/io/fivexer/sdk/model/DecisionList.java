package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for {@code GET /v1/decisions}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only.
 */
public class DecisionList {
    private List<Decision> decisions;

    public List<Decision> getDecisions() { return decisions; }
}
