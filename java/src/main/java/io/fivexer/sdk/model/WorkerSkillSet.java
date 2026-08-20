package io.fivexer.sdk.model;

import java.util.List;

/**
 * A worker's skills after replacing the whole set.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerSkillSet {
    private String workerId;
    private List<WorkerSkill> skills;

    public String getWorkerId() { return workerId; }
    public List<WorkerSkill> getSkills() { return skills; }
}
