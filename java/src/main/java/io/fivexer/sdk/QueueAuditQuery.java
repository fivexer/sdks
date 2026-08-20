package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/** Filters for {@link Fivexer#queueAudit(QueueAuditQuery)}. Every field is optional. */
public final class QueueAuditQuery {
    private Integer limit;
    private Long minWaitingMs;
    private Boolean includeHealthy;

    public QueueAuditQuery limit(Integer limit) { this.limit = limit; return this; }

    public QueueAuditQuery minWaitingMs(Long minWaitingMs) { this.minWaitingMs = minWaitingMs; return this; }

    public QueueAuditQuery includeHealthy(Boolean includeHealthy) {
        this.includeHealthy = includeHealthy;
        return this;
    }

    /** Render as query parameters, dropping everything that was never set. */
    Map<String, String> toQuery() {
        return Json.query(
                "limit", Json.str(limit),
                "minWaitingMs", Json.str(minWaitingMs),
                // Lower-cased explicitly: Fastify parses the string form, and Java's
                // Boolean.toString() already agrees — but pinning it here keeps the wire
                // independent of that happening to match.
                "includeHealthy", includeHealthy == null ? null : (includeHealthy ? "true" : "false"));
    }
}
