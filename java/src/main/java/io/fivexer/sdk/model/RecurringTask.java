package io.fivexer.sdk.model;

import java.util.List;

/**
 * A standing template with its clock, from {@code client.tasks().recurring().list()}.
 *
 * <p>The template is never matchable and never appears in {@code tasks().list()} or the queue
 * stats — only the occurrences cut from it do.
 *
 * <p>Populated by Gson via field reflection and exposed read-only.
 */
public class RecurringTask {

    private String id;
    private List<String> tags;
    private Double priority;
    private String title;
    private RecurrencePolicy recurrence;
    private long nextAt;
    private int occurrences;

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }
    public String getTitle() { return title; }
    public RecurrencePolicy getRecurrence() { return recurrence; }

    /** Epoch ms the next occurrence's window opens. */
    public long getNextAt() { return nextAt; }

    /** Occurrences materialized so far. */
    public int getOccurrences() { return occurrences; }
}
