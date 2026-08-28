package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.RecurringTask;
import io.fivexer.sdk.model.RecurringTaskList;
import java.util.Collections;
import java.util.List;
import java.util.Map;

/**
 * Standing templates that occurrences are cut from.
 *
 * <p>A template is created through {@link Tasks#create} with a {@code recurrence} — there is no
 * separate create here. It is never itself matchable and never appears in {@link Tasks#list},
 * {@link Tasks#scheduled} or the queue stats; only the occurrences cut from it do, so a caller
 * showing someone their standing work has to read this collection rather than the task list.
 */
public final class TaskRecurring {

    private final Fivexer client;

    TaskRecurring(Fivexer client) {
        this.client = client;
    }

    /** Templates with their clocks, soonest next occurrence first. */
    public List<RecurringTask> list() {
        RecurringTaskList envelope = client.request("GET", "/tasks/recurring", null, null,
                RecurringTaskList.class, false);
        return envelope.getRecurring() == null ? Collections.emptyList() : envelope.getRecurring();
    }

    /** Stop a template. Occurrences already materialized live on. */
    public void remove(String templateId) {
        remove(templateId, false);
    }

    /**
     * Stop a template.
     *
     * <p>Throws {@link FivexerApiException} with a 404 on an unknown id rather than succeeding
     * quietly — removing a template twice is not idempotent here, and swallowing the 404 would
     * mask a wrong id.
     *
     * @param dropScheduled also discard the occurrences already cut from it. Sent only when
     *     true: those occurrences are real scheduled tasks someone may be about to work, and a
     *     {@code false} on the wire would look like a caller who considered them and declined.
     */
    public void remove(String templateId, boolean dropScheduled) {
        Map<String, String> query = dropScheduled ? Map.of("dropScheduled", "true") : null;
        client.request("DELETE", "/tasks/recurring/" + Json.enc(templateId), null, query,
                Void.class, true);
    }
}
