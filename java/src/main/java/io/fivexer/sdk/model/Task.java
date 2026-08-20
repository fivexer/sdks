package io.fivexer.sdk.model;

import java.util.List;
import java.util.Map;

/**
 * A task on the /v1 surface. Timestamps are epoch-milliseconds; {@code meta} is an arbitrary object.
 *
 * <p>Response models are populated by Gson via field reflection and exposed read-only; callers
 * receive instances from the client and never construct them.
 */
public class Task {
    private String id;
    private List<String> tags;
    private Double priority;       // null when absent
    private String status;         // queued|pending|accepted|completed|cancelled
    private String workerId;       // null when unassigned
    private Long createdAt;        // epoch-ms, null when absent
    private Map<String, Object> meta;
    private Map<String, Object> result;  // only on archived reads
    private boolean archived;
    private String title;              // truncated copy; the full value lives on tasks().context()
    private Double latitude;
    private Double longitude;
    private Double maxDistanceKm;
    private Boolean requireGeo;
    private List<String> allowedCidrs;
    private String workflowRunId;      // set on workflow-step tasks
    private String workflowStepId;
    private TaskDataSummary data;      // single-task reads only, never in lists

    public String getId() { return id; }
    public List<String> getTags() { return tags; }
    public Double getPriority() { return priority; }
    public String getStatus() { return status; }
    public String getWorkerId() { return workerId; }
    public Long getCreatedAt() { return createdAt; }
    public Map<String, Object> getMeta() { return meta; }
    public Map<String, Object> getResult() { return result; }
    public boolean isArchived() { return archived; }
    public String getTitle() { return title; }
    public Double getLatitude() { return latitude; }
    public Double getLongitude() { return longitude; }
    public Double getMaxDistanceKm() { return maxDistanceKm; }
    public Boolean getRequireGeo() { return requireGeo; }
    public List<String> getAllowedCidrs() { return allowedCidrs; }
    public String getWorkflowRunId() { return workflowRunId; }
    public String getWorkflowStepId() { return workflowStepId; }
    public TaskDataSummary getData() { return data; }
}
