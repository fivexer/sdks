package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Input for a QR join link: preset tags, skills and team for whoever scans it.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class CreateJoinLink {
    private String label;
    private String teamId;
    private List<String> tags;
    private List<WorkerSkillAssignment> skills;
    private Boolean requiresApproval;
    private Integer maxUses;
    private Long expiresInMs;

    public CreateJoinLink(String label) {
        if (label == null) {
            throw new IllegalArgumentException("label is required");
        }
        this.label = label;
    }

    public String getLabel() { return label; }
    public String getTeamId() { return teamId; }
    public List<String> getTags() { return tags; }
    public List<WorkerSkillAssignment> getSkills() { return skills; }
    public Boolean getRequiresApproval() { return requiresApproval; }
    public Integer getMaxUses() { return maxUses; }
    public Long getExpiresInMs() { return expiresInMs; }

    public CreateJoinLink teamId(String teamId) { this.teamId = teamId; return this; }
    public CreateJoinLink tags(List<String> tags) { this.tags = tags; return this; }
    public CreateJoinLink skills(List<WorkerSkillAssignment> skills) { this.skills = skills; return this; }
    public CreateJoinLink requiresApproval(Boolean requiresApproval) { this.requiresApproval = requiresApproval; return this; }
    public CreateJoinLink maxUses(Integer maxUses) { this.maxUses = maxUses; return this; }
    public CreateJoinLink expiresInMs(Long expiresInMs) { this.expiresInMs = expiresInMs; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
