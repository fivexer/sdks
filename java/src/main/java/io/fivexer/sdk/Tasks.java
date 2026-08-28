package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.BulkTaskReport;
import io.fivexer.sdk.model.TaskCheckReport;
import io.fivexer.sdk.model.TaskEscalation;
import io.fivexer.sdk.model.TaskList;
import io.fivexer.sdk.model.UnparkTask;
import io.fivexer.sdk.model.AssignTaskResult;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.SuggestWorkers;
import io.fivexer.sdk.model.SuggestWorkersResult;
import io.fivexer.sdk.model.Task;
import io.fivexer.sdk.model.TaskAction;
import io.fivexer.sdk.model.TaskPage;
import io.fivexer.sdk.model.TaskPriority;
import java.util.Map;

/** The task lifecycle, plus the operator overrides and the rich-data sub-resources. */
public final class Tasks {

    private final Fivexer client;
    private final TaskContexts contextResource;
    private final TaskComments commentsResource;
    private final TaskAttachments attachmentsResource;
    private final TaskRecurring recurringResource;

    Tasks(Fivexer client) {
        this.client = client;
        this.contextResource = new TaskContexts(client);
        this.commentsResource = new TaskComments(client);
        this.attachmentsResource = new TaskAttachments(client);
        this.recurringResource = new TaskRecurring(client);
    }

    /** The rich data stored against a task: title, description, context and references. */
    public TaskContexts context() { return contextResource; }

    /** Comments on a task. */
    public TaskComments comments() { return commentsResource; }

    /** Files attached to a task. */
    public TaskAttachments attachments() { return attachmentsResource; }

    /** Standing templates that occurrences are cut from. */
    public TaskRecurring recurring() { return recurringResource; }

    /** Enqueue a task. The response carries the id and {@code queued} status. */
    public Task create(CreateTask input) {
        return client.request("POST", "/tasks", input.toJson(), null, Task.class, false);
    }

    public Task get(String taskId) {
        return client.request("GET", "/tasks/" + Json.enc(taskId), null, null, Task.class, false);
    }

    public TaskPage list(ListTasksQuery query) {
        ListTasksQuery q = query == null ? new ListTasksQuery() : query;
        return client.request("GET", "/tasks", null, q.toQuery(), TaskPage.class, false);
    }

    public void cancel(String taskId) {
        client.request("DELETE", "/tasks/" + Json.enc(taskId), null, null, Void.class, true);
    }

    /**
     * Create many tasks in one call.
     *
     * <p>Partial success is normal — a 200 does not mean every task was created. Read {@code
     * failed} and the per-entry results, whose {@code index} maps back to this list.
     */
    public BulkTaskReport createMany(java.util.List<CreateTask> tasks) {
        com.google.gson.JsonArray array = new com.google.gson.JsonArray();
        for (CreateTask task : tasks) {
            array.add(com.google.gson.JsonParser.parseString(task.toJson()));
        }
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.add("tasks", array);
        return client.request("POST", "/tasks/bulk", body.toString(), null,
                BulkTaskReport.class, false);
    }

    /**
     * Dry-run a task: how many workers could take it, which of its tags nobody covers, and what
     * is wrong with its policies. Creates nothing and reserves nothing.
     */
    public TaskCheckReport check(CreateTask input) {
        return client.request("POST", "/tasks/check", input.toJson(), null,
                TaskCheckReport.class, false);
    }

    /** Acknowledge an offer without starting work — stops the response clock only. */
    public TaskAction ack(String taskId, String workerId) {
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/ack",
                Json.workerAction(workerId, null), null, TaskAction.class, false);
    }

    /**
     * Advance the escalation ladder now. {@code parked} comes back true when the ladder was
     * already exhausted, which takes the task out of matching.
     */
    public TaskEscalation escalate(String taskId, String workerId) {
        String body = workerId == null ? "{}" : Json.object("workerId", workerId);
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/escalate", body, null,
                TaskEscalation.class, false);
    }

    public TaskEscalation escalate(String taskId) {
        return escalate(taskId, null);
    }

    /**
     * Tasks a policy gave up on — an exhausted escalation ladder, an SLA breach handled with
     * park, or a rejection budget run dry. Out of matching, but recoverable with {@link #unpark}.
     */
    public TaskList parked() {
        return client.request("GET", "/tasks/parked", null, null, TaskList.class, false);
    }

    /**
     * Tasks held by a {@code schedule.notBefore}, soonest activation first — the only view of
     * work that has been booked but has not started.
     */
    public TaskList scheduled() {
        return client.request("GET", "/tasks/scheduled", null, null, TaskList.class, false);
    }

    /**
     * Return a parked task to the queue. Reset the clocks that parked it, or the next sweep may
     * park it straight back.
     */
    public TaskAction unpark(String taskId, UnparkTask options) {
        String body = options == null ? "{}" : options.toJson();
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/unpark", body, null,
                TaskAction.class, false);
    }

    public TaskAction unpark(String taskId) {
        return unpark(taskId, null);
    }

    public TaskAction accept(String taskId, String workerId) {
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/accept",
                Json.workerAction(workerId, null), null, TaskAction.class, false);
    }

    /** Reject a task — it requeues for other eligible workers. */
    public TaskAction reject(String taskId, String workerId) {
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/reject",
                Json.workerAction(workerId, null), null, TaskAction.class, false);
    }

    public TaskAction complete(String taskId, String workerId, Map<String, Object> result) {
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/complete",
                Json.workerAction(workerId, result), null, TaskAction.class, false);
    }

    /**
     * Operator override: hand a task to a specific worker. Queued tasks go through the same
     * claim gate as organic matching; a pending task is transferred and its expiry clock
     * restarts. The result names the worker it was taken from, if any.
     */
    public AssignTaskResult assign(String taskId, String workerId) {
        return assign(taskId, workerId, false);
    }

    /**
     * @param force bypass the paused/backlog/veto/prior-rejection checks (worker existence is
     *     still enforced)
     */
    public AssignTaskResult assign(String taskId, String workerId, boolean force) {
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.addProperty("workerId", workerId);
        if (force) {
            body.addProperty("force", true);
        }
        return client.request("POST", "/tasks/" + Json.enc(taskId) + "/assign",
                body.toString(), null, AssignTaskResult.class, false);
    }

    public TaskPriority setPriority(String taskId, double priority) {
        return client.request("PATCH", "/tasks/" + Json.enc(taskId),
                Json.object("priority", priority), null, TaskPriority.class, false);
    }

    /** Dry run: who <em>would</em> match these tags, without creating a task. */
    public SuggestWorkersResult suggestWorkers(SuggestWorkers input) {
        return client.request("POST", "/tasks/suggest-workers", input.toJson(), null,
                SuggestWorkersResult.class, false);
    }
}
