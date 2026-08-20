package io.fivexer.sdk.model;

import java.util.List;

/**
 * A QR join link. {@code maxUses} is null for unlimited; 0 would mean exhausted.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class JoinLink {
    private String id;
    private String label;
    private String status;
    private String teamId;
    private List<String> tags;
    private Boolean requiresApproval;
    private Integer maxUses;
    private Integer useCount;
    private String createdAt;
    private String expiresAt;
    private String revokedAt;

    public String getId() { return id; }
    public String getLabel() { return label; }
    public String getStatus() { return status; }
    public String getTeamId() { return teamId; }
    public List<String> getTags() { return tags; }
    public Boolean getRequiresApproval() { return requiresApproval; }
    public Integer getMaxUses() { return maxUses; }
    public Integer getUseCount() { return useCount; }
    public String getCreatedAt() { return createdAt; }
    public String getExpiresAt() { return expiresAt; }
    public String getRevokedAt() { return revokedAt; }
}
