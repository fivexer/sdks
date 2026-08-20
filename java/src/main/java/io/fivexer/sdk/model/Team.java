package io.fivexer.sdk.model;

/**
 * A team: a routing primitive, not a label — matching sees the derived {@code tag}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class Team {
    private String id;
    private String key;
    private String tag;
    private String name;
    private String description;
    private String color;
    private String createdAt;

    public String getId() { return id; }
    public String getKey() { return key; }
    public String getTag() { return tag; }
    public String getName() { return name; }
    public String getDescription() { return description; }
    public String getColor() { return color; }
    public String getCreatedAt() { return createdAt; }
}
