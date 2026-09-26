package io.fivexer.sdk.model;

/**
 * A qualification that has lapsed, or lapses soon.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class ExpiringWorkerSkill {
    private String workerId;
    private String label;
    private String skillId;
    private String key;
    private String name;
    private String validUntil;
    private boolean expired;

    public String getWorkerId() { return workerId; }

    /** The person's portal label where they have one, otherwise their worker id. */
    public String getLabel() { return label; }

    public String getSkillId() { return skillId; }
    public String getKey() { return key; }
    public String getName() { return name; }

    /** Always set — a qualification with no expiry is never in this list. */
    public String getValidUntil() { return validUntil; }

    /** Already lapsed, judged against the request's {@code asOf} day. */
    public boolean isExpired() { return expired; }
}
