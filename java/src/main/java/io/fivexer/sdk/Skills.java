package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.PatchSkill;
import io.fivexer.sdk.model.Skill;
import io.fivexer.sdk.model.SkillList;
import io.fivexer.sdk.model.UpsertSkill;
import java.util.Collections;
import java.util.List;

/** The workspace skill catalogue. */
public final class Skills {

    private final Fivexer client;

    Skills(Fivexer client) {
        this.client = client;
    }

    public Skill create(UpsertSkill input) {
        return client.request("POST", "/skills", input.toJson(), null, Skill.class, false);
    }

    public List<Skill> list() {
        return list(null, null);
    }

    /** @param q free-text filter over key and name */
    public List<Skill> list(String q, Integer limit) {
        SkillList envelope = client.request("GET", "/skills", null,
                Json.query("q", q, "limit", Json.str(limit)), SkillList.class, false);
        return unwrap(envelope);
    }

    public Skill get(String skillId) {
        return client.request("GET", "/skills/" + Json.enc(skillId), null, null, Skill.class, false);
    }

    /** Partial update — the {@code key} is immutable. */
    public Skill patch(String skillId, PatchSkill patch) {
        return client.request("PATCH", "/skills/" + Json.enc(skillId), patch.toJson(), null,
                Skill.class, false);
    }

    public void remove(String skillId) {
        client.request("DELETE", "/skills/" + Json.enc(skillId), null, null, Void.class, true);
    }

    /**
     * Skills commonly held alongside the ones already chosen. The list travels as one
     * comma-joined query parameter; an empty selection omits it entirely.
     */
    public List<Skill> suggest(List<String> selected, Integer limit) {
        String joined = (selected == null || selected.isEmpty()) ? null : String.join(",", selected);
        SkillList envelope = client.request("GET", "/skills/suggest", null,
                Json.query("selected", joined, "limit", Json.str(limit)), SkillList.class, false);
        return unwrap(envelope);
    }

    public List<Skill> suggest() {
        return suggest(null, null);
    }

    private static List<Skill> unwrap(SkillList envelope) {
        return envelope.getSkills() == null ? Collections.emptyList() : envelope.getSkills();
    }
}
