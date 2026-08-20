package io.fivexer.sdk;

import io.fivexer.sdk.model.Decision;
import io.fivexer.sdk.model.DecisionList;
import java.util.Collections;
import java.util.List;

/** Match decisions: who was considered for a task, and why the winner won. */
public final class Decisions {

    private final Fivexer client;

    Decisions(Fivexer client) {
        this.client = client;
    }

    public List<Decision> list(ListDecisionsQuery query) {
        ListDecisionsQuery q = query == null ? new ListDecisionsQuery() : query;
        DecisionList envelope = client.request("GET", "/decisions", null, q.toQuery(), DecisionList.class, false);
        return envelope.getDecisions() == null ? Collections.emptyList() : envelope.getDecisions();
    }

    public List<Decision> list() {
        return list(null);
    }
}
