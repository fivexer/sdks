package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.TeamPresence;
import io.fivexer.sdk.model.TeamTimeResult;

/**
 * The supervisor view of presence — who is working, on break, or paused — and the team's
 * recorded working time. Paused is an operator action; on-break is the worker's own, and the two
 * are reported separately. Requires the control plane.
 */
public final class Team {

    private final Fivexer client;

    Team(Fivexer client) {
        this.client = client;
    }

    public TeamPresence presence() {
        return presence(null);
    }

    /** @param teamId narrow the view to one crew, or null for the whole workspace */
    public TeamPresence presence(String teamId) {
        return client.request("GET", "/team/presence", null, Json.query("teamId", teamId),
                TeamPresence.class, false);
    }

    /** The team's shift, break and worked time for today. */
    public TeamTimeResult time() {
        return time(null);
    }

    /**
     * Worked time, breaks and outcomes per worker (and per UTC day, with {@code entries}) for a
     * window — the working-time report.
     */
    public TeamTimeResult time(TeamTimeQuery query) {
        TeamTimeQuery q = query == null ? new TeamTimeQuery() : query;
        return client.request("GET", "/team/time", null, q.toQuery(), TeamTimeResult.class, false);
    }
}
