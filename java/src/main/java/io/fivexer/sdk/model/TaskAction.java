package io.fivexer.sdk.model;

import java.util.Map;

/** Response of {@code /tasks/{id}/accept|reject|complete}: the task id and its new status. */
public class TaskAction {
    private String id;
    private String status;

    public String getId() { return id; }
    public String getStatus() { return status; }
}
