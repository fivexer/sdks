package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.Map;

/**
 * Input for starting a workflow run.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class StartRun {
    private Map<String, Object> context;
    private String initiatorWorkerId;  // required when a step assigns to the initiator

    public StartRun() {}

    public Map<String, Object> getContext() { return context; }
    public String getInitiatorWorkerId() { return initiatorWorkerId; }

    public StartRun context(Map<String, Object> context) { this.context = context; return this; }
    public StartRun initiatorWorkerId(String initiatorWorkerId) { this.initiatorWorkerId = initiatorWorkerId; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
