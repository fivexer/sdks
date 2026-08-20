package io.fivexer.sdk.model;

import java.util.List;

/**
 * Queue depth and per-worker load.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class QueueStats {
    private Long oldestWaitingMs;  // age of the longest-waiting unaccepted task; null when the queue is empty
    private List<WorkerLoad> perWorker;

    public Long getOldestWaitingMs() { return oldestWaitingMs; }
    public List<WorkerLoad> getPerWorker() { return perWorker; }
}
