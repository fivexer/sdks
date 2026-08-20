package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for self-registering through a QR join link. The worker id is generated server-side.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class JoinWorkspace {
    private String token;
    private String name;
    private String pin;
    private String email;

    public JoinWorkspace(String token, String name, String pin) {
        if (token == null) {
            throw new IllegalArgumentException("token is required");
        }
        if (name == null) {
            throw new IllegalArgumentException("name is required");
        }
        if (pin == null) {
            throw new IllegalArgumentException("pin is required");
        }
        this.token = token;
        this.name = name;
        this.pin = pin;
    }

    public String getToken() { return token; }
    public String getName() { return name; }
    public String getPin() { return pin; }
    public String getEmail() { return email; }

    public JoinWorkspace email(String email) { this.email = email; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
