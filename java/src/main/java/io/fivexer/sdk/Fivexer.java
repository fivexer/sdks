package io.fivexer.sdk;

import com.google.gson.Gson;
import com.google.gson.GsonBuilder;
import com.google.gson.JsonDeserializer;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import io.fivexer.sdk.model.AttachmentUpload;
import io.fivexer.sdk.model.DecisionCandidate;
import io.fivexer.sdk.model.QuotaInfo;
import io.fivexer.sdk.model.WorkflowStep;
import io.fivexer.sdk.model.WorkspaceStats;
import java.util.List;
import java.util.Map;
import okhttp3.HttpUrl;
import okhttp3.MediaType;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

/**
 * Synchronous typed client for the Fivexer Platform {@code /v1} API, authenticated with a
 * workspace API key ({@code sk_test_}/{@code sk_live_}).
 *
 * <p>Zero hard dependencies beyond OkHttp + Gson. Inject an {@link OkHttpClient} (pointed at a
 * MockWebServer) for testing. For the worker-facing portal surface, which uses a {@code wt_}
 * session token scoped to a single worker, use {@link FivexerWorker} instead.
 *
 * <pre>{@code
 * Fivexer client = new Fivexer("https://api.fivexer.com", "sk_test_...");
 * client.workers().upsert(new UpsertWorker("agent_1").tags(List.of("english")));
 * Task task = client.tasks().create(new CreateTask(List.of("english")).priority(90));
 * }</pre>
 *
 * <p>Every quota-checked response updates {@link #getQuota()}, so an integration can back off
 * before hitting 402/429 — and those errors carry the same snapshot.
 */
public class Fivexer implements AutoCloseable {

    static final MediaType JSON_TYPE = MediaType.get("application/json; charset=utf-8");
    private static final Gson PAYLOAD_GSON = new Gson();

    /**
     * Splits a decision candidate's workerId from its other detail fields. Reason kinds are
     * additive over time, so the rest is kept as a free-form map rather than a rigid schema.
     */
    private static final JsonDeserializer<DecisionCandidate> DECISION_CANDIDATE_DESERIALIZER =
            (json, type, ctx) -> {
                JsonObject obj = json.getAsJsonObject();
                DecisionCandidate c = new DecisionCandidate();
                @SuppressWarnings("unchecked")
                Map<String, Object> detail = PAYLOAD_GSON.fromJson(obj, Map.class);
                if (obj.has("workerId") && obj.get("workerId").isJsonPrimitive()) {
                    c.setWorkerId(obj.get("workerId").getAsString());
                    detail.remove("workerId");
                }
                c.setDetail(detail);
                return c;
            };

    /**
     * A step that ends the workflow carries an explicit {@code defaultNextStepId: null}, which
     * plain field reflection cannot tell apart from an absent key. Recording the distinction
     * here is what lets a definition survive a read/write round trip unchanged.
     */
    private static final JsonDeserializer<WorkflowStep> WORKFLOW_STEP_DESERIALIZER =
            (json, type, ctx) -> {
                JsonObject obj = json.getAsJsonObject();
                WorkflowStep step = PAYLOAD_GSON.fromJson(obj, WorkflowStep.class);
                if (obj.has("defaultNextStepId") && obj.get("defaultNextStepId").isJsonNull()) {
                    step.endWorkflow();
                }
                return step;
            };

    private final String baseUrl;
    private final String apiKey;
    private final OkHttpClient httpClient;
    private final int maxRetries;
    private final Gson gson;

    private volatile QuotaInfo quota;

    private final Tasks tasksResource;
    private final Workers workersResource;
    private final Skills skillsResource;
    private final Decisions decisionsResource;
    private final Workflows workflowsResource;
    private final Runs runsResource;
    private final Learning learningResource;
    private final Notifications notificationsResource;
    private final History historyResource;
    private final Team teamResource;
    private final Breaks breaksResource;
    private final Teams teamsResource;
    private final JoinLinks joinLinksResource;
    private final Identities identitiesResource;

    public Fivexer(String baseUrl, String apiKey) {
        this(baseUrl, apiKey, null, 1);
    }

    public Fivexer(String baseUrl, String apiKey, int maxRetries) {
        this(baseUrl, apiKey, null, maxRetries);
    }

    public Fivexer(String baseUrl, String apiKey, OkHttpClient httpClient) {
        this(baseUrl, apiKey, httpClient, 1);
    }

    /**
     * The resource groups hold a back-reference to this client so they can share one transport,
     * one quota snapshot and one Gson. That hands out {@code this} from the constructor, which
     * JDK 21's {@code this-escape} lint flags for a non-final class — it is safe here because
     * the resources only stash the reference and never call back during construction.
     */
    @SuppressWarnings("this-escape")
    public Fivexer(String baseUrl, String apiKey, OkHttpClient httpClient, int maxRetries) {
        if (baseUrl == null || baseUrl.isEmpty()) {
            throw new IllegalArgumentException("baseUrl is required");
        }
        if (apiKey == null || apiKey.isEmpty()) {
            throw new IllegalArgumentException("apiKey is required");
        }
        this.baseUrl = baseUrl.replaceAll("/+$", "");
        this.apiKey = apiKey;
        this.maxRetries = maxRetries;
        this.httpClient = httpClient != null ? httpClient : new OkHttpClient();
        this.gson = new GsonBuilder()
                .registerTypeAdapter(DecisionCandidate.class, DECISION_CANDIDATE_DESERIALIZER)
                .registerTypeAdapter(WorkflowStep.class, WORKFLOW_STEP_DESERIALIZER)
                .create();

        this.tasksResource = new Tasks(this);
        this.workersResource = new Workers(this);
        this.skillsResource = new Skills(this);
        this.decisionsResource = new Decisions(this);
        this.workflowsResource = new Workflows(this);
        this.runsResource = new Runs(this);
        this.learningResource = new Learning(this);
        this.notificationsResource = new Notifications(this);
        this.historyResource = new History(this);
        this.teamResource = new Team(this);
        this.breaksResource = new Breaks(this);
        this.teamsResource = new Teams(this);
        this.joinLinksResource = new JoinLinks(this);
        this.identitiesResource = new Identities(this);
    }

    public String getBaseUrl() { return baseUrl; }

    /** Latest X-Quota-* snapshot seen on any response (success or error). */
    public QuotaInfo getQuota() { return quota; }

    public Tasks tasks() { return tasksResource; }
    public Workers workers() { return workersResource; }
    public Skills skills() { return skillsResource; }
    public Teams teams() { return teamsResource; }
    public JoinLinks joinLinks() { return joinLinksResource; }
    public Identities identities() { return identitiesResource; }
    public Decisions decisions() { return decisionsResource; }
    public Workflows workflows() { return workflowsResource; }
    public Runs runs() { return runsResource; }
    public Learning learning() { return learningResource; }
    public Notifications notifications() { return notificationsResource; }

    /** Historical stats from the archive. Requires the control plane. */
    public History history() { return historyResource; }

    /** Supervisor presence view. Requires the control plane. */
    public Team team() { return teamResource; }

    /** Supervisor break rollups. Requires the control plane. */
    public Breaks breaks() { return breaksResource; }

    public WorkspaceStats stats() {
        return request("GET", "/stats", null, null, WorkspaceStats.class, false);
    }

    /** SLO counters, workspace-wide or for one tag. */
    public io.fivexer.sdk.model.SlaStats slaStats(String tag) {
        return request("GET", "/stats/sla", null, io.fivexer.sdk.internal.Json.query("tag", tag),
                io.fivexer.sdk.model.SlaStats.class, false);
    }

    public io.fivexer.sdk.model.SlaStats slaStats() {
        return slaStats(null);
    }

    /**
     * Why the queue is not draining. Walks the queue against the live roster, so it is heavier
     * than {@link #stats()} — poll it on a dashboard's cadence, not a request's.
     */
    public io.fivexer.sdk.model.QueueAuditReport queueAudit(QueueAuditQuery query) {
        return request("GET", "/stats/queue-audit", null,
                query == null ? null : query.toQuery(),
                io.fivexer.sdk.model.QueueAuditReport.class, false);
    }

    public io.fivexer.sdk.model.QueueAuditReport queueAudit() {
        return queueAudit(null);
    }

    /** Where workers log in, and whether the portal is switched on at all. */
    public io.fivexer.sdk.model.WorkerPortalLink portal() {
        return request("GET", "/portal", null, null,
                io.fivexer.sdk.model.WorkerPortalLink.class, false);
    }

    @Override
    public void close() {
        // OkHttpClient is thread-safe and does not require explicit closing; no resources
        // to release. Implementing AutoCloseable lets callers use try-with-resources for clarity.
    }

    // ---- core transport ----

    /**
     * @param body a JSON string to send, or null for no body
     * @param voidExpected true when the endpoint answers with no content
     */
    <T> T request(String method, String path, String body, Map<String, String> query, Class<T> type,
            boolean voidExpected) {
        // Generate the idempotency key ONCE so a retried POST carries the same key and is
        // deduplicated server-side rather than creating a second task.
        String idempotencyKey = "POST".equals(method) ? java.util.UUID.randomUUID().toString() : null;
        int attempt = 0;
        while (true) {
            Request okRequest = buildRequest(method, path, body, query, idempotencyKey);
            int status;
            String rawBody;
            QuotaInfo capturedQuota;
            String retryAfter;
            try (Response response = httpClient.newCall(okRequest).execute()) {
                status = response.code();
                capturedQuota = parseQuota(response.headers().toMultimap());
                if (capturedQuota.hasAny()) {
                    this.quota = capturedQuota;
                }
                retryAfter = response.header("retry-after");
                if (attempt < maxRetries && isRetryable(status)) {
                    Double wait = retryAfterSeconds(retryAfter);
                    if (wait != null) {
                        sleepQuietly((long) (wait * 1000));
                    }
                    attempt++;
                    continue;
                }
                rawBody = response.body() != null ? response.body().string() : "";
            } catch (Exception e) {
                throw new FivexerException("HTTP request failed: " + e.getMessage(), e);
            }

            if (status >= 400) {
                throw toApiException(status, rawBody, capturedQuota, retryAfterSeconds(retryAfter));
            }
            if (status == 204 || voidExpected) {
                return null;
            }
            return gson.fromJson(rawBody, type);
        }
    }

    /**
     * PUT attachment bytes straight to object storage. Not a {@code /v1} call: no API key, no
     * retry, and the presigned headers go out exactly as issued because they are signed.
     */
    void putBytes(AttachmentUpload upload, byte[] payload) {
        Request.Builder rb = new Request.Builder()
                .url(upload.getUrl())
                .method(upload.getMethod() == null ? "PUT" : upload.getMethod(),
                        RequestBody.create(payload));
        if (upload.getHeaders() != null) {
            for (Map.Entry<String, String> e : upload.getHeaders().entrySet()) {
                rb.header(e.getKey(), e.getValue());
            }
        }
        int status;
        try (Response response = httpClient.newCall(rb.build()).execute()) {
            status = response.code();
        } catch (java.io.IOException e) {
            throw new FivexerException("attachment upload failed: " + e.getMessage(), e);
        }
        if (status >= 400) {
            throw new FivexerApiException(status, "upload_failed",
                    "storage upload failed with http " + status, null, null);
        }
    }

    private Request buildRequest(String method, String path, String body, Map<String, String> query,
            String idempotencyKey) {
        HttpUrl.Builder urlBuilder = HttpUrl.parse(baseUrl + "/v1" + path).newBuilder();
        if (query != null) {
            for (Map.Entry<String, String> e : query.entrySet()) {
                urlBuilder.addQueryParameter(e.getKey(), e.getValue());
            }
        }
        Request.Builder rb = new Request.Builder()
                .url(urlBuilder.build())
                .header("Authorization", "Bearer " + apiKey)
                .header("Accept", "application/json");

        if (body != null) {
            rb.method(method, RequestBody.create(body, JSON_TYPE));
            rb.header("Content-Type", "application/json");
        } else {
            rb.method(method, emptyBodyIfRequired(method));
        }
        if (idempotencyKey != null) {
            rb.header("Idempotency-Key", idempotencyKey);
        }
        return rb.build();
    }

    /**
     * OkHttp refuses a bodyless POST/PUT/PATCH, but several endpoints legitimately take no body
     * (confirm an attachment, cancel a run, reset learning, log out). Those get a zero-length
     * body — and deliberately no Content-Type, since there is no content to describe.
     */
    static RequestBody emptyBodyIfRequired(String method) {
        boolean requiresBody = "POST".equals(method) || "PUT".equals(method) || "PATCH".equals(method);
        return requiresBody ? RequestBody.create(new byte[0], null) : null;
    }

    private static boolean isRetryable(int status) {
        return status == 429 || status >= 500;
    }

    private void sleepQuietly(long millis) {
        if (millis <= 0) {
            return;
        }
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }

    private FivexerApiException toApiException(int status, String rawBody, QuotaInfo quota, Double retryAfter) {
        String code = "unknown_error";
        String message = "http " + status;
        try {
            JsonObject root = JsonParser.parseString(rawBody).getAsJsonObject();
            if (root.has("error") && root.get("error").isJsonObject()) {
                JsonObject err = root.getAsJsonObject("error");
                if (err.has("code") && err.get("code").isJsonPrimitive()) {
                    code = err.get("code").getAsString();
                }
                if (err.has("message") && err.get("message").isJsonPrimitive()) {
                    message = err.get("message").getAsString();
                }
            }
        } catch (Exception ignored) {
            // A non-JSON body (a gateway error page, say) still yields a structured exception.
        }
        return new FivexerApiException(status, code, message, quota, retryAfter);
    }

    static Double retryAfterSeconds(String value) {
        if (value == null || value.isEmpty()) {
            return null;
        }
        try {
            return Math.max(0.0, Double.parseDouble(value));
        } catch (NumberFormatException e) {
            return null;
        }
    }

    private static QuotaInfo parseQuota(Map<String, List<String>> headers) {
        QuotaInfo q = new QuotaInfo();
        q.setTaskRateLimit(intHeader(headers, "x-quota-task-rate-limit"));
        q.setTaskRateRemaining(intHeader(headers, "x-quota-task-rate-remaining"));
        q.setQueuedTasksLimit(intHeader(headers, "x-quota-queued-tasks-limit"));
        q.setQueuedTasksRemaining(intHeader(headers, "x-quota-queued-tasks-remaining"));
        q.setWorkersLimit(intHeader(headers, "x-quota-workers-limit"));
        q.setWorkersRemaining(intHeader(headers, "x-quota-workers-remaining"));
        q.setSkillsLimit(intHeader(headers, "x-quota-skills-limit"));
        q.setSkillsRemaining(intHeader(headers, "x-quota-skills-remaining"));
        q.setWorkerSkillsLimit(intHeader(headers, "x-quota-worker-skills-limit"));
        q.setWorkerSkillsRemaining(intHeader(headers, "x-quota-worker-skills-remaining"));
        return q;
    }

    private static Integer intHeader(Map<String, List<String>> headers, String name) {
        for (Map.Entry<String, List<String>> e : headers.entrySet()) {
            if (name.equalsIgnoreCase(e.getKey())) {
                try {
                    return Integer.parseInt(e.getValue().get(0));
                } catch (NumberFormatException | IndexOutOfBoundsException ex) {
                    return null;
                }
            }
        }
        return null;
    }
}
