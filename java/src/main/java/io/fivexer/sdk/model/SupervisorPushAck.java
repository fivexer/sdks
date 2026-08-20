package io.fivexer.sdk.model;

/**
 * The supervisor push table is keyed by endpoint and has nothing else to hand back, so subscribing answers with a bare acknowledgement rather than a subscription record.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SupervisorPushAck {
    private Boolean ok;

    public Boolean getOk() { return ok; }
}
