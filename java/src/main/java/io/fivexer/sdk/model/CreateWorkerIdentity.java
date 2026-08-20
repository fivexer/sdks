package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for setting a worker's PIN directly, for someone who will never receive email (a kiosk, an agent).
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class CreateWorkerIdentity {
    private String label;
    private String pin;
    private String email;

    public CreateWorkerIdentity(String label, String pin) {
        if (label == null) {
            throw new IllegalArgumentException("label is required");
        }
        if (pin == null) {
            throw new IllegalArgumentException("pin is required");
        }
        this.label = label;
        this.pin = pin;
    }

    public String getLabel() { return label; }
    public String getPin() { return pin; }
    public String getEmail() { return email; }

    public CreateWorkerIdentity email(String email) { this.email = email; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
