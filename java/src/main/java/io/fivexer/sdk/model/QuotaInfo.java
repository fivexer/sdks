package io.fivexer.sdk.model;

import java.io.Serializable;
import java.util.Map;

/**
 * Parsed from the {@code X-Quota-*} headers of the most recent response that carried them.
 * Any field may be {@code null} when the corresponding header was absent.
 */
public class QuotaInfo implements Serializable {
    private static final long serialVersionUID = 1L;

    private Integer taskRateLimit;
    private Integer taskRateRemaining;
    private Integer queuedTasksLimit;
    private Integer queuedTasksRemaining;
    private Integer workersLimit;
    private Integer workersRemaining;
    private Integer skillsLimit;
    private Integer skillsRemaining;
    private Integer workerSkillsLimit;
    private Integer workerSkillsRemaining;

    public Integer getTaskRateLimit() { return taskRateLimit; }
    public Integer getTaskRateRemaining() { return taskRateRemaining; }
    public Integer getQueuedTasksLimit() { return queuedTasksLimit; }
    public Integer getQueuedTasksRemaining() { return queuedTasksRemaining; }
    public Integer getWorkersLimit() { return workersLimit; }
    public Integer getWorkersRemaining() { return workersRemaining; }
    public Integer getSkillsLimit() { return skillsLimit; }
    public Integer getSkillsRemaining() { return skillsRemaining; }
    public Integer getWorkerSkillsLimit() { return workerSkillsLimit; }
    public Integer getWorkerSkillsRemaining() { return workerSkillsRemaining; }

    public void setTaskRateLimit(Integer v) { this.taskRateLimit = v; }
    public void setTaskRateRemaining(Integer v) { this.taskRateRemaining = v; }
    public void setQueuedTasksLimit(Integer v) { this.queuedTasksLimit = v; }
    public void setQueuedTasksRemaining(Integer v) { this.queuedTasksRemaining = v; }
    public void setWorkersLimit(Integer v) { this.workersLimit = v; }
    public void setWorkersRemaining(Integer v) { this.workersRemaining = v; }
    public void setSkillsLimit(Integer v) { this.skillsLimit = v; }
    public void setSkillsRemaining(Integer v) { this.skillsRemaining = v; }
    public void setWorkerSkillsLimit(Integer v) { this.workerSkillsLimit = v; }
    public void setWorkerSkillsRemaining(Integer v) { this.workerSkillsRemaining = v; }

    /** @return {@code true} if at least one quota header was present. */
    public boolean hasAny() {
        // Non-short-circuiting OR: every field is evaluated, so JaCoCo sees a single
        // branch-free expression rather than six short-circuit branches.
        return taskRateLimit != null
                | taskRateRemaining != null
                | queuedTasksLimit != null
                | queuedTasksRemaining != null
                | workersLimit != null
                | workersRemaining != null
                | skillsLimit != null
                | skillsRemaining != null
                | workerSkillsLimit != null
                | workerSkillsRemaining != null;
    }
}
