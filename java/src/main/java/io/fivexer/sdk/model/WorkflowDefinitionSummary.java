package io.fivexer.sdk.model;

/**
 * A workflow definition as it appears in a listing.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkflowDefinitionSummary {
    private String id;
    private String name;

    public String getId() { return id; }
    public String getName() { return name; }
}
