package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * Full detail of one worker, including current load.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerDetail {
    private String id;
    private List<String> tags;
    private Map<String, Double> routingWeights;
    // Which entries of routingWeights the learning layer owns, and when it last wrote them — the
    // difference between "an operator vetoed this tag" and "the model did", which is the
    // difference between a decision to keep and one to revert.
    private Map<String, Double> learnedRoutingWeights;
    // What routingWeights held before the last sync; restored by learning().revertWeights()
    private Map<String, Double> routingWeightsSnapshot;
    private Long learnedRoutingWeightsSyncedAt;
    private List<WorkerSkill> skills;  // null when skills are not in use for this worker
    private List<WorkerTeam> teams;    // null when this worker is in no teams
    private Integer maxBacklogSize;  // null when no per-worker cap is set; 0 means receive nothing
    private boolean available;
    // Why they are unavailable when it is not simply "off shift": an invite nobody accepted, or
    // a QR join waiting on operator approval. An operator UI that renders either as "paused"
    // tells the wrong story — nobody has to resume an invite, they have to chase it.
    private boolean invitePending;
    private boolean pendingApproval;
    private int queueDepth;
    /** Null when they have never chosen one and the workspace default applies. */
    private String locale;

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Map<String, Double> getRoutingWeights() { return routingWeights; }
    public Map<String, Double> getLearnedRoutingWeights() { return learnedRoutingWeights; }
    public Map<String, Double> getRoutingWeightsSnapshot() { return routingWeightsSnapshot; }
    public Long getLearnedRoutingWeightsSyncedAt() { return learnedRoutingWeightsSyncedAt; }
    public List<WorkerSkill> getSkills() { return skills; }
    public List<WorkerTeam> getTeams() { return teams; }
    public Integer getMaxBacklogSize() { return maxBacklogSize; }
    public boolean isAvailable() { return available; }
    public boolean isInvitePending() { return invitePending; }
    public boolean isPendingApproval() { return pendingApproval; }
    public int getQueueDepth() { return queueDepth; }

    /** The worker's language — what their push notifications and emails are composed in. */
    public String getLocale() { return locale; }
}
