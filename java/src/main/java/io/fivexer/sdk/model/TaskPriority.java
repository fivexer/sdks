package io.fivexer.sdk.model;

/**
 * The result of repricing a task.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TaskPriority {
    private String id;
    private double priority;

    public String getId() { return id; }
    public double getPriority() { return priority; }
}
