package io.fivexer.sdk.model;

import java.util.List;

/**
 * A delivery target. The signing secret is write-only and never returned.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class NotificationChannel {
    private String id;
    private String type;
    private String target;
    private List<String> events;
    private boolean disabled;
    private String createdAt;  // ISO-8601

    public String getId() { return id; }
    public String getType() { return type; }
    public String getTarget() { return target; }
    public List<String> getEvents() { return events; }
    public boolean isDisabled() { return disabled; }
    public String getCreatedAt() { return createdAt; }
}
