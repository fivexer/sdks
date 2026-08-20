package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for a worker changing their own PIN from the portal.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class ChangePin {
    private String currentPin;
    private String newPin;

    public ChangePin(String currentPin, String newPin) {
        if (currentPin == null) {
            throw new IllegalArgumentException("currentPin is required");
        }
        if (newPin == null) {
            throw new IllegalArgumentException("newPin is required");
        }
        this.currentPin = currentPin;
        this.newPin = newPin;
    }

    public String getCurrentPin() { return currentPin; }
    public String getNewPin() { return newPin; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
