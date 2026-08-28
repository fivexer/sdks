package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.TeamPresence;

/**
 * The supervisor view of presence: who is working, on break, or paused. Paused is an operator
 * action; on-break is the worker's own, and the two are reported separately. Requires the
 * control plane.
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
}
