package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.SetTaskContext;
import io.fivexer.sdk.model.TaskContext;

/** The rich data stored against a task. Writes replace the stored context wholesale. */
public final class TaskContexts {

    private final Fivexer client;

    TaskContexts(Fivexer client) {
        this.client = client;
    }

    public TaskContext get(String taskId) {
        return client.request("GET", "/tasks/" + Json.enc(taskId) + "/context", null, null,
                TaskContext.class, false);
    }

    public TaskContext set(String taskId, SetTaskContext input) {
        return client.request("PUT", "/tasks/" + Json.enc(taskId) + "/context", input.toJson(), null,
                TaskContext.class, false);
    }

    public void clear(String taskId) {
        client.request("DELETE", "/tasks/" + Json.enc(taskId) + "/context", null, null, Void.class, true);
    }
}
