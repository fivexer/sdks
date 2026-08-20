package io.fivexer.sdk.model;

/**
 * A skill in the workspace catalogue.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class Skill {
    private String id;
    private String key;
    private String name;
    private String description;
    private String createdAt;  // ISO-8601

    public String getId() { return id; }
    public String getKey() { return key; }
    public String getName() { return name; }
    public String getDescription() { return description; }
    public String getCreatedAt() { return createdAt; }
}
