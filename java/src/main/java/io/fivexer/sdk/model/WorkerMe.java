package io.fivexer.sdk.model;

import java.util.List;

/**
 * Who am I, and am I on shift? Works on data-plane-only deployments, where the break and team endpoints 501 — {@code onBreak} is simply always false there.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerMe {
    private String workerId;
    private String label;
    private Boolean available;
    private Boolean pendingApproval;
    private Boolean onBreak;
    private String breakStartedAt;
    private List<WorkerSkill> skills;
    private Boolean skillSetupPending;
    private String locale;
    /** Whether the automatic working-time notices still reach this worker's inbox. */
    private Boolean emailNotices;
    /** Which kinds of push this worker still wants, keyed by category id. */
    private java.util.Map<String, Boolean> pushCategories;

    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public Boolean getAvailable() { return available; }
    public Boolean getPendingApproval() { return pendingApproval; }
    public Boolean getOnBreak() { return onBreak; }
    public String getBreakStartedAt() { return breakStartedAt; }
    public List<WorkerSkill> getSkills() { return skills; }
    public Boolean getSkillSetupPending() { return skillSetupPending; }
    public String getLocale() { return locale; }
    public Boolean getEmailNotices() { return emailNotices; }
    public java.util.Map<String, Boolean> getPushCategories() { return pushCategories; }
}
