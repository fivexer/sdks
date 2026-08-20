package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for consuming an emailed invite token and setting the PIN. The token is single-use.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class AcceptWorkerInvite {
    private String token;
    private String pin;

    public AcceptWorkerInvite(String token, String pin) {
        if (token == null) {
            throw new IllegalArgumentException("token is required");
        }
        if (pin == null) {
            throw new IllegalArgumentException("pin is required");
        }
        this.token = token;
        this.pin = pin;
    }

    public String getToken() { return token; }
    public String getPin() { return pin; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
