package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.PatchTeam;
import io.fivexer.sdk.model.Team;
import io.fivexer.sdk.model.TeamList;
import io.fivexer.sdk.model.TeamMembers;
import io.fivexer.sdk.model.TeamRoster;
import io.fivexer.sdk.model.UpsertTeam;
import java.util.Collections;
import java.util.List;

/**
 * Teams.
 *
 * <p>A team is a routing primitive, not a label: the server derives a {@code tag} from the key
 * and matching sees that tag, so adding a worker to a team changes what work reaches them.
 */
public final class Teams {

    private final Fivexer client;

    Teams(Fivexer client) {
        this.client = client;
    }

    public Team create(UpsertTeam input) {
        return client.request("POST", "/teams", input.toJson(), null, Team.class, false);
    }

    public List<Team> list() {
        TeamList envelope = client.request("GET", "/teams", null, null, TeamList.class, false);
        return envelope.getTeams() == null ? Collections.emptyList() : envelope.getTeams();
    }

    public Team get(String teamId) {
        return client.request("GET", "/teams/" + Json.enc(teamId), null, null, Team.class, false);
    }

    /** Partial update — the {@code key} is immutable. */
    public Team patch(String teamId, PatchTeam patch) {
        return client.request("PATCH", "/teams/" + Json.enc(teamId), patch.toJson(), null,
                Team.class, false);
    }

    public void remove(String teamId) {
        client.request("DELETE", "/teams/" + Json.enc(teamId), null, null, Void.class, true);
    }

    public TeamMembers members(String teamId) {
        return client.request("GET", "/teams/" + Json.enc(teamId) + "/members", null, null,
                TeamMembers.class, false);
    }

    /** Replaces the roster wholesale — a worker absent from {@code workerIds} is removed. */
    public TeamRoster setMembers(String teamId, List<String> workerIds) {
        return client.request("PUT", "/teams/" + Json.enc(teamId) + "/members",
                Json.object("workerIds", workerIds), null, TeamRoster.class, false);
    }
}
