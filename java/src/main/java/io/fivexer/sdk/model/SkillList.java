package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for the skill listing endpoints.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SkillList {
    private List<Skill> skills;

    public List<Skill> getSkills() { return skills; }
}
