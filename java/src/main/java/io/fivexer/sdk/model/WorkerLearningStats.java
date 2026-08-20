package io.fivexer.sdk.model;

import java.util.List;

/**
 * What the model has learned about one worker.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerLearningStats {
    private String workerId;
    private List<WorkerLearningSkillStat> skills;

    public String getWorkerId() { return workerId; }
    public List<WorkerLearningSkillStat> getSkills() { return skills; }
}
