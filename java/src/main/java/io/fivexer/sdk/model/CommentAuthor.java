package io.fivexer.sdk.model;

/**
 * {@code user} = console human, {@code worker} = on behalf of a worker, {@code api} = the integration.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class CommentAuthor {
    private String type;
    private String id;
    private String label;

    public String getType() { return type; }
    public String getId() { return id; }
    public String getLabel() { return label; }
}
