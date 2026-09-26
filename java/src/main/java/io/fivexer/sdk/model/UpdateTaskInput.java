package io.fivexer.sdk.model;

import com.google.gson.JsonNull;
import com.google.gson.JsonObject;
import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * Input for {@code PATCH /v1/tasks/{id}} — an edit to a live task.
 *
 * <p>Patch semantics: a field never set is omitted and left alone. The rich-data fields can also
 * be cleared, which is an explicit {@code null} on the wire — a null argument cannot say that,
 * because it is indistinguishable from "not set", so each clearable field has its own
 * {@code clearX()} method, following {@link PatchWorker#clearLocale()}. Calling the setter after
 * a clear (or the clear after a setter) keeps whichever came last.
 *
 * <p>Changing {@code tags} changes who is eligible: when the new tags no longer reach the worker
 * holding the task, it is taken back and requeued — {@link UpdateTaskResult#isRequeued()} says so.
 *
 * <pre>{@code
 * client.tasks().update("task_1", new UpdateTaskInput()
 *         .tags(List.of("billing", "priority"))
 *         .clearDescription());
 * }</pre>
 */
public class UpdateTaskInput {
    private List<String> tags;
    private Double priority;
    private String title;
    private String description;
    private Map<String, Object> context;
    private List<TaskReference> references;
    private Map<String, Object> meta;

    // SDK bookkeeping, invisible to Gson: which fields to send as an explicit null.
    private transient boolean clearTitle;
    private transient boolean clearDescription;
    private transient boolean clearContext;
    private transient boolean clearReferences;
    private transient boolean clearMeta;

    public UpdateTaskInput() {}

    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }
    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public Map<String, Object> getContext() { return context; }
    public List<TaskReference> getReferences() { return references; }
    public Map<String, Object> getMeta() { return meta; }

    /** Replace the routing tags. May requeue the task off its current holder. */
    public UpdateTaskInput tags(List<String> tags) { this.tags = tags; return this; }

    public UpdateTaskInput priority(double priority) { this.priority = priority; return this; }

    public UpdateTaskInput title(String title) { this.title = title; this.clearTitle = false; return this; }

    /** Send {@code "title": null}. */
    public UpdateTaskInput clearTitle() { this.title = null; this.clearTitle = true; return this; }

    public UpdateTaskInput description(String description) {
        this.description = description;
        this.clearDescription = false;
        return this;
    }

    /** Send {@code "description": null}. */
    public UpdateTaskInput clearDescription() {
        this.description = null;
        this.clearDescription = true;
        return this;
    }

    public UpdateTaskInput context(Map<String, Object> context) {
        this.context = context;
        this.clearContext = false;
        return this;
    }

    /** Send {@code "context": null}. */
    public UpdateTaskInput clearContext() { this.context = null; this.clearContext = true; return this; }

    public UpdateTaskInput references(List<TaskReference> references) {
        this.references = references;
        this.clearReferences = false;
        return this;
    }

    /** Send {@code "references": null}. */
    public UpdateTaskInput clearReferences() {
        this.references = null;
        this.clearReferences = true;
        return this;
    }

    public UpdateTaskInput meta(Map<String, Object> meta) { this.meta = meta; this.clearMeta = false; return this; }

    /** Send {@code "meta": null}. */
    public UpdateTaskInput clearMeta() { this.meta = null; this.clearMeta = true; return this; }

    /** Serialise the set fields, then write the requested clears as explicit nulls. */
    public String toJson() {
        JsonObject o = Json.tree(this).getAsJsonObject();
        if (clearTitle) o.add("title", JsonNull.INSTANCE);
        if (clearDescription) o.add("description", JsonNull.INSTANCE);
        if (clearContext) o.add("context", JsonNull.INSTANCE);
        if (clearReferences) o.add("references", JsonNull.INSTANCE);
        if (clearMeta) o.add("meta", JsonNull.INSTANCE);
        return o.toString();
    }
}
