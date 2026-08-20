package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;
import java.util.List;
import java.util.Map;

/**
 * Input for {@code tasks().context().set(...)} — a full replace of a task's rich data.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class SetTaskContext {
    private String title;
    private String description;
    private Map<String, Object> context;
    private List<TaskReference> references;

    public SetTaskContext() {}

    public String getTitle() { return title; }
    public String getDescription() { return description; }
    public Map<String, Object> getContext() { return context; }
    public List<TaskReference> getReferences() { return references; }

    public SetTaskContext title(String title) { this.title = title; return this; }
    public SetTaskContext description(String description) { this.description = description; return this; }
    public SetTaskContext context(Map<String, Object> context) { this.context = context; return this; }
    public SetTaskContext references(List<TaskReference> references) { this.references = references; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
