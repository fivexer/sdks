package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.AddComment;
import io.fivexer.sdk.model.Comment;
import io.fivexer.sdk.model.CommentEnvelope;
import io.fivexer.sdk.model.CommentPage;

/** Comments on a task, cursor-paginated newest-first. */
public final class TaskComments {

    private final Fivexer client;

    TaskComments(Fivexer client) {
        this.client = client;
    }

    public Comment add(String taskId, AddComment input) {
        CommentEnvelope envelope = client.request("POST", "/tasks/" + Json.enc(taskId) + "/comments",
                input.toJson(), null, CommentEnvelope.class, false);
        return envelope.getComment();
    }

    public CommentPage list(String taskId, String cursor, Integer limit) {
        return client.request("GET", "/tasks/" + Json.enc(taskId) + "/comments", null,
                Json.query("cursor", cursor, "limit", Json.str(limit)), CommentPage.class, false);
    }

    public CommentPage list(String taskId) {
        return list(taskId, null, null);
    }

    public void remove(String taskId, String commentId) {
        client.request("DELETE", "/tasks/" + Json.enc(taskId) + "/comments/" + Json.enc(commentId),
                null, null, Void.class, true);
    }
}
