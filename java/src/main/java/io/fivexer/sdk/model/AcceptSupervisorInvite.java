package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for redeeming a supervisor link.
 *
 * <p>The token is single-use. Expired, already-used, revoked and never-existed all answer with
 * the same 400 {@code invalid_token} — the server refuses to tell a grinder which half of a
 * guess was right, so no caller can distinguish them either.
 */
public class AcceptSupervisorInvite {
    private String token;

    public AcceptSupervisorInvite(String token) {
        if (token == null) {
            throw new IllegalArgumentException("token is required");
        }
        this.token = token;
    }

    public String getToken() { return token; }

    /** Serialise for the wire. */
    public String toJson() {
        return Json.write(this);
    }
}
