package io.fivexer.sdk.model;

import java.util.List;

/**
 * Which workers had their learned routing weights restored.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class RevertedWeights {
    private List<String> reverted;
    private Integer count;

    public List<String> getReverted() { return reverted; }
    public Integer getCount() { return count; }
}
