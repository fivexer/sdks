package io.fivexer.sdk.model;

import java.util.List;

/** Result of {@code GET /v1/workers} — the list of worker ids and the count. */
public class WorkerList {
    private List<String> workers;
    private int count;

    public List<String> getWorkers() { return workers; }
    public int getCount() { return count; }
}
