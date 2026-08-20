package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;

/**
 * Input for emailing a worker a set-your-PIN link. Creates the worker too when it does not exist yet, which the result reports as {@code workerCreated}.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class InviteWorkerIdentity {
    private String email;
    private String workerId;
    private String label;
    private List<String> tags;
    private List<WorkerSkillAssignment> skills;
    private List<String> teamIds;

    public InviteWorkerIdentity(String email) {
        if (email == null) {
            throw new IllegalArgumentException("email is required");
        }
        this.email = email;
    }

    public String getEmail() { return email; }
    public String getWorkerId() { return workerId; }
    public String getLabel() { return label; }
    public List<String> getTags() { return tags; }
    public List<WorkerSkillAssignment> getSkills() { return skills; }
    public List<String> getTeamIds() { return teamIds; }

    public InviteWorkerIdentity workerId(String workerId) { this.workerId = workerId; return this; }
    public InviteWorkerIdentity label(String label) { this.label = label; return this; }
    public InviteWorkerIdentity tags(List<String> tags) { this.tags = tags; return this; }
    public InviteWorkerIdentity skills(List<WorkerSkillAssignment> skills) { this.skills = skills; return this; }
    public InviteWorkerIdentity teamIds(List<String> teamIds) { this.teamIds = teamIds; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
