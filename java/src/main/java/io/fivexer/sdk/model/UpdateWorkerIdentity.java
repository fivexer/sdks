package io.fivexer.sdk.model;

import com.google.gson.JsonObject;

/**
 * Partial update of a worker identity. Revoking is a {@code status} change here, not a DELETE —
 * the worker stays routable, they just lose the ability to sign in.
 *
 * <p>Unlike every other input model this one does not serialise through {@link
 * io.fivexer.sdk.internal.Json#write(Object)}. Gson omits nulls, which is the right rule almost
 * everywhere — but {@code email} is genuinely nullable on this endpoint: absent keeps the stored
 * address, an explicit null erases it. Expressing "erase" therefore needs a flag and a
 * hand-built body, because {@code email(null)} is indistinguishable from never calling it.
 */
public class UpdateWorkerIdentity {
    private String label;
    private String email;
    private String pin;
    private String status;
    private boolean clearEmail;

    public UpdateWorkerIdentity() {}

    public String getLabel() { return label; }
    public String getEmail() { return email; }
    public String getPin() { return pin; }
    public String getStatus() { return status; }

    /** True when {@link #clearEmail()} was called, meaning the body carries an explicit null. */
    public boolean isClearingEmail() { return clearEmail; }

    public UpdateWorkerIdentity label(String label) { this.label = label; return this; }
    public UpdateWorkerIdentity pin(String pin) { this.pin = pin; return this; }
    public UpdateWorkerIdentity status(String status) { this.status = status; return this; }

    /** Set a new address. Passing null here is a no-op; use {@link #clearEmail()} to erase. */
    public UpdateWorkerIdentity email(String email) {
        this.email = email;
        this.clearEmail = false;
        return this;
    }

    /** Erase the stored email address, sending an explicit {@code null}. */
    public UpdateWorkerIdentity clearEmail() {
        this.email = null;
        this.clearEmail = true;
        return this;
    }

    /** Serialise for the wire, omitting unset fields but preserving an explicit email null. */
    public String toJson() {
        JsonObject o = new JsonObject();
        if (label != null) {
            o.addProperty("label", label);
        }
        if (pin != null) {
            o.addProperty("pin", pin);
        }
        if (status != null) {
            o.addProperty("status", status);
        }
        if (email != null) {
            o.addProperty("email", email);
        } else if (clearEmail) {
            o.add("email", com.google.gson.JsonNull.INSTANCE);
        }
        return o.toString();
    }
}
