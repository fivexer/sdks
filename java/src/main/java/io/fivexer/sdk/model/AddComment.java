package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Input for adding a comment to a task.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class AddComment {
    private String body;
    private String workerId;  // attribute the comment to a worker (API-key callers only)
    private String authorLabel;

    public AddComment(String body) {
        if (body == null) {
            throw new IllegalArgumentException("body is required");
        }
        this.body = body;
    }

    public String getBody() { return body; }
    public String getWorkerId() { return workerId; }
    public String getAuthorLabel() { return authorLabel; }

    public AddComment workerId(String workerId) { this.workerId = workerId; return this; }
    public AddComment authorLabel(String authorLabel) { this.authorLabel = authorLabel; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
