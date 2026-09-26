package io.fivexer.sdk.model;

import java.util.List;

/** Envelope for {@code GET /v1/skills/expiring}: already-expired rows first. */
public class ExpiringSkillsResult {

    private String asOf;
    private List<ExpiringWorkerSkill> skills;

    /** The day expiry was judged against ({@code YYYY-MM-DD}). */
    public String getAsOf() { return asOf; }

    public List<ExpiringWorkerSkill> getSkills() { return skills; }
}
