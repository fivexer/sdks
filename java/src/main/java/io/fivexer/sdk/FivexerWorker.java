package io.fivexer.sdk;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.AcceptWorkerInvite;
import io.fivexer.sdk.model.AttachmentUpload;
import io.fivexer.sdk.model.AcceptWorkerInviteResult;
import io.fivexer.sdk.model.ChangePin;
import io.fivexer.sdk.model.Comment;
import io.fivexer.sdk.model.CommentEnvelope;
import io.fivexer.sdk.model.CommentPage;
import io.fivexer.sdk.model.JoinWorkspace;
import io.fivexer.sdk.model.JoinWorkspaceResult;
import io.fivexer.sdk.model.PushConfig;
import io.fivexer.sdk.model.PushSubscription;
import io.fivexer.sdk.model.PushSubscriptionInput;
import io.fivexer.sdk.model.Skill;
import io.fivexer.sdk.model.SkillList;
import io.fivexer.sdk.model.TaskAction;
import io.fivexer.sdk.model.TeamPresence;
import io.fivexer.sdk.model.VoiceIceServers;
import io.fivexer.sdk.model.WorkerBreakEnded;
import io.fivexer.sdk.model.WorkerBreakStarted;
import io.fivexer.sdk.model.WorkerAvailabilityState;
import io.fivexer.sdk.model.WorkerBreakToday;
import io.fivexer.sdk.model.WorkerDevice;
import io.fivexer.sdk.model.WorkerDeviceInput;
import io.fivexer.sdk.model.WorkerLocation;
import io.fivexer.sdk.model.WorkerLocationResult;
import io.fivexer.sdk.model.WorkerLogin;
import io.fivexer.sdk.model.WorkerMe;
import io.fivexer.sdk.model.WorkerMetricsToday;
import io.fivexer.sdk.model.WorkerMetricsWindow;
import io.fivexer.sdk.model.WorkerQueue;
import io.fivexer.sdk.model.WorkerSessionToken;
import io.fivexer.sdk.model.WorkerSkillLevel;
import io.fivexer.sdk.model.WorkerSkillSet;
import io.fivexer.sdk.model.WorkerTaskDetail;
import io.fivexer.sdk.model.WorkerTimeEntriesResult;
import java.util.Collections;
import java.util.List;
import java.util.Map;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

/**
 * Worker-facing client for the hosted portal — the {@code wt_} worker-token plane.
 *
 * <p>Deliberately separate from {@link Fivexer} (workspace {@code sk_} key): a worker token is
 * scoped to exactly one worker and can never reach task-creation or worker-management routes,
 * so the type system says so too.
 *
 * <p>{@link #login} adopts both the returned token and the worker id, so later calls need no
 * extra wiring:
 *
 * <pre>{@code
 * FivexerWorker worker = new FivexerWorker("https://api.fivexer.com");
 * worker.login(new WorkerLogin("ws_1", "agent_1", "4821"));
 * WorkerQueue queue = worker.queue();
 * worker.accept(queue.getTaskIds().get(0));
 * }</pre>
 *
 * <p>Unlike the workspace plane this surface emits no {@code X-Quota-*} headers and carries no
 * idempotency key, so only reads are retried — replaying an accept could claim a task twice.
 */
public class FivexerWorker implements AutoCloseable {

    private static final Gson GSON = new Gson();

    private final String baseUrl;
    private final OkHttpClient httpClient;
    private final int maxRetries;

    private volatile String token;
    private volatile String workerId;
    private final WorkerAttachments attachmentsResource;

    public FivexerWorker(String baseUrl) {
        this(baseUrl, null, null, null, 1);
    }

    /** Resume a prior session from a stored token. */
    public FivexerWorker(String baseUrl, String token, String workerId) {
        this(baseUrl, token, workerId, null, 1);
    }

    /**
     * The attachments resource holds a back-reference to this client so it shares one transport
     * and one session token. That hands out {@code this} from the constructor, which JDK 21's
     * {@code this-escape} lint flags for a non-final class — safe here because the resource only
     * stashes the reference and never calls back during construction, exactly as on
     * {@link Fivexer}.
     */
    @SuppressWarnings("this-escape")
    public FivexerWorker(String baseUrl, String token, String workerId, OkHttpClient httpClient, int maxRetries) {
        if (baseUrl == null || baseUrl.isEmpty()) {
            throw new IllegalArgumentException("baseUrl is required");
        }
        this.baseUrl = baseUrl.replaceAll("/+$", "");
        this.token = token;
        this.workerId = workerId;
        this.maxRetries = maxRetries;
        this.httpClient = httpClient != null ? httpClient : new OkHttpClient();
        this.attachmentsResource = new WorkerAttachments(this);
    }

    public String getBaseUrl() { return baseUrl; }

    /** The current worker session token, if logged in. */
    public String getSessionToken() { return token; }

    /** The worker this client acts as. */
    public String getWorkerId() { return workerId; }

    /** Adopt a token (and optionally the worker id it belongs to) from a prior session. */
    public void setToken(String token, String workerId) {
        this.token = token;
        if (workerId != null) {
            this.workerId = workerId;
        }
    }

    @Override
    public void close() {
        // OkHttpClient needs no explicit close; AutoCloseable is for try-with-resources clarity.
    }

    // ---- auth ----

    /** Log in and adopt the returned token plus the worker id for subsequent calls. */
    public WorkerSessionToken login(WorkerLogin credentials) {
        WorkerSessionToken session = request("POST", "/worker-auth/login", credentials.toJson(),
                WorkerSessionToken.class, false);
        this.token = session.getToken();
        this.workerId = credentials.getWorkerId();
        return session;
    }

    /** Revoke the session and forget the token. */
    public void logout() {
        request("POST", "/worker-auth/logout", null, Void.class, true);
        this.token = null;
    }

    // ---- tasks ----

    /** This worker's current queue. */
    public WorkerQueue queue() {
        return queue(null);
    }

    public WorkerQueue queue(String forWorkerId) {
        return request("GET", "/portal/workers/" + Json.enc(require(forWorkerId)) + "/queue", null,
                WorkerQueue.class, false);
    }

    /** Rich detail of a task assigned to this worker; another worker's task answers 404. */
    public WorkerTaskDetail taskDetail(String taskId) {
        return request("GET", "/portal/tasks/" + Json.enc(taskId), null, WorkerTaskDetail.class, false);
    }

    public TaskAction accept(String taskId) {
        return accept(taskId, null);
    }

    public TaskAction accept(String taskId, String forWorkerId) {
        return action(taskId, "accept", forWorkerId);
    }

    /** Reject a task — it requeues for other eligible workers. */
    public TaskAction reject(String taskId) {
        return reject(taskId, null);
    }

    public TaskAction reject(String taskId, String forWorkerId) {
        return action(taskId, "reject", forWorkerId);
    }

    public TaskAction complete(String taskId) {
        return complete(taskId, null, null);
    }

    public TaskAction complete(String taskId, Map<String, Object> result) {
        return complete(taskId, result, null);
    }

    public TaskAction complete(String taskId, Map<String, Object> result, String forWorkerId) {
        return request("POST", "/portal/tasks/" + Json.enc(taskId) + "/complete",
                Json.workerAction(require(forWorkerId), result), TaskAction.class, false);
    }

    private TaskAction action(String taskId, String verb, String forWorkerId) {
        return request("POST", "/portal/tasks/" + Json.enc(taskId) + "/" + verb,
                Json.workerAction(require(forWorkerId), null), TaskAction.class, false);
    }

    // ---- breaks, metrics, presence ----

    /**
     * Start a break: routing to this worker pauses while the current backlog is kept. Answers
     * 409 when a break is already open.
     */
    public WorkerBreakStarted startBreak() {
        return startBreak(null);
    }

    public WorkerBreakStarted startBreak(String reason) {
        JsonObject body = new JsonObject();
        if (reason != null) {
            body.addProperty("reason", reason);
        }
        return request("POST", "/portal/breaks/start", body.toString(), WorkerBreakStarted.class, false);
    }

    /**
     * End the break and resume routing.
     *
     * @return null when no break was open — the API answers 204, which is a normal outcome for
     *     a portal UI polling this, not an error
     */
    public WorkerBreakEnded endBreak() {
        return request("POST", "/portal/breaks/end", null, WorkerBreakEnded.class, false);
    }

    /** This worker's own breaks for today, including the one still running. */
    public WorkerBreakToday breaksToday() {
        return request("GET", "/portal/breaks/today", null, WorkerBreakToday.class, false);
    }

    /** This worker's own metrics for today: completions, break time, working time. */
    public WorkerMetricsToday metricsToday() {
        return request("GET", "/portal/metrics/today", null, WorkerMetricsToday.class, false);
    }

    /**
     * This worker's own recorded time log — shifts and the breaks inside them, for the last 7
     * days, with no parameters to narrow it.
     *
     * <p>The same record an operator reads through {@code workers().timeEntries()}. That parity is
     * deliberate: it is what keeps the log a timesheet rather than surveillance.
     */
    public WorkerTimeEntriesResult timeEntries() {
        return request("GET", "/portal/me/time-entries", null, WorkerTimeEntriesResult.class, false);
    }

    /** Read-only team presence — visible to every worker in the workspace. */
    public TeamPresence teamPresence() {
        return request("GET", "/portal/team/presence", null, TeamPresence.class, false);
    }

    // ---- sessions that mint a token ----

    /**
     * Rotate the session token in place.
     *
     * <p>Returns false when the server says re-authenticate (any 4xx) — the caller's job there is
     * a login prompt, not an exception. Anything else (5xx, network, an older server with no
     * route) propagates, leaving the current token usable for the caller's own retry.
     */
    public boolean refresh() {
        if (token == null || token.isEmpty()) {
            return false;
        }
        WorkerSessionToken session;
        try {
            session = request("POST", "/worker-auth/refresh", null, WorkerSessionToken.class, false);
        } catch (FivexerApiException e) {
            if (e.getStatusCode() >= 400 && e.getStatusCode() < 500) {
                return false;
            }
            throw e;
        }
        this.token = session.getToken();
        return true;
    }

    /**
     * Self-register through a QR join link, adopting the returned session.
     *
     * <p>The worker id is generated server-side — show it to them, it is the username they type
     * at the PIN screen next time. When the link required approval, {@code pendingApproval} is
     * true and no work routes until an operator admits them.
     */
    public JoinWorkspaceResult join(JoinWorkspace input) {
        JoinWorkspaceResult result = request("POST", "/worker-auth/join", input.toJson(),
                JoinWorkspaceResult.class, false);
        setToken(result.getToken(), result.getWorkerId());
        return result;
    }

    /**
     * Consume an emailed invite token and set this worker's PIN, adopting the returned session.
     *
     * <p>Setting a PIN is the sign-in — otherwise the worker's next act after choosing one would
     * be to retype it into a login form, which is what the magic link exists to avoid. The token
     * is single-use: replaying it is a 400 {@code invalid_token}, same as an expired link.
     */
    public AcceptWorkerInviteResult acceptInvite(AcceptWorkerInvite input) {
        AcceptWorkerInviteResult result = request("POST", "/worker-auth/accept-invite",
                input.toJson(), AcceptWorkerInviteResult.class, false);
        setToken(result.getToken(), result.getWorkerId());
        return result;
    }

    // ---- shift and identity ----

    /**
     * Who am I, and am I on shift? Works on data-plane-only deployments, where the break and team
     * endpoints 501 ({@code onBreak} is simply always false there).
     */
    public WorkerMe me() {
        return request("GET", "/portal/me", null, WorkerMe.class, false);
    }

    /**
     * Go on or off shift — the worker's own switch. Workers are created off shift, so this is what
     * makes someone matchable in the first place. Off shift keeps the existing backlog; going
     * available also ends an open break. Throws 403 {@code approval_pending} while an operator
     * still has to admit a QR-join worker.
     */
    public WorkerAvailabilityState setAvailability(boolean available) {
        return setAvailability(available, null);
    }

    /**
     * The same switch with a liveness contract attached, for an <em>unattended</em> worker only.
     *
     * <p>{@code staleAfterMs} (60_000–86_400_000) authorises the platform to close the shift and
     * pause routing after that much silence from this worker — which is what stops a crashed
     * daemon reading as available forever, and shows up in the time log as
     * {@code endReason: "timeout"}. An interactive client must never send it: a person working
     * away from their phone is not a crashed process.
     *
     * <p>Only meaningful alongside {@code available: true}, so it is sent only then.
     *
     * @param staleAfterMs the silence budget in milliseconds, or null for no liveness contract
     */
    public WorkerAvailabilityState setAvailability(boolean available, Long staleAfterMs) {
        JsonObject body = new JsonObject();
        body.addProperty("available", available);
        if (available && staleAfterMs != null) {
            body.addProperty("staleAfterMs", staleAfterMs);
        }
        return request("POST", "/portal/me/availability", body.toString(),
                WorkerAvailabilityState.class, false);
    }

    /** A wrong current PIN is a 400 ({@code invalid_current_pin}); the session survives either way. */
    public void changePin(ChangePin input) {
        request("POST", "/portal/me/pin", input.toJson(), Void.class, true);
    }

    // ---- own skills ----

    /**
     * The workspace's skill catalogue — what this worker may claim. Read-only: inventing a skill
     * is an operator's decision, so a worker picks from this list or picks nothing.
     */
    public List<Skill> skillCatalog() {
        SkillList envelope = request("GET", "/portal/skills", null, SkillList.class, false);
        return envelope.getSkills() == null ? Collections.emptyList() : envelope.getSkills();
    }

    /**
     * Declare what this worker can do, replacing their whole set. Does not change shift state:
     * {@code WorkerMe.skillSetupPending} asks the question, this answers it, and going on shift
     * stays a separate claim.
     */
    public WorkerSkillSet setSkills(List<WorkerSkillLevel> skills) {
        com.google.gson.JsonArray array = new com.google.gson.JsonArray();
        for (WorkerSkillLevel skill : skills) {
            array.add(com.google.gson.JsonParser.parseString(skill.toJson()));
        }
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.add("skills", array);
        return request("PUT", "/portal/me/skills", body.toString(), WorkerSkillSet.class, false);
    }

    // ---- comments, metrics, location ----

    public CommentPage comments(String taskId) {
        return comments(taskId, null, null);
    }

    public CommentPage comments(String taskId, String cursor, Integer limit) {
        return request("GET", "/portal/tasks/" + Json.enc(taskId) + "/comments", null,
                Json.query("cursor", cursor, "limit", Json.str(limit)), CommentPage.class, false);
    }

    public Comment addComment(String taskId, String body) {
        CommentEnvelope envelope = request("POST", "/portal/tasks/" + Json.enc(taskId) + "/comments",
                Json.object("body", body), CommentEnvelope.class, false);
        return envelope.getComment();
    }

    public WorkerMetricsWindow metricsWindow() {
        return metricsWindow("7d");
    }

    public WorkerMetricsWindow metricsWindow(String window) {
        return request("GET", "/portal/metrics", null, Json.query("window", window),
                WorkerMetricsWindow.class, false);
    }

    /** Rate-limited server-side (about one per 10s) to keep churn off the matching data plane. */
    public WorkerLocationResult updateLocation(WorkerLocation location) {
        return request("POST", "/portal/workers/me/location", location.toJson(),
                WorkerLocationResult.class, false);
    }

    // ---- push notifications ----

    /** Register an Expo push token for the native app. Browsers use {@link #pushSubscribe} instead. */
    public WorkerDevice registerDevice(WorkerDeviceInput device) {
        return request("POST", "/portal/devices", device.toJson(), WorkerDevice.class, false);
    }

    /** Call on logout, or when notification permission is revoked. */
    public void unregisterDevice(String deviceToken) {
        request("DELETE", "/portal/devices", Json.object("token", deviceToken), Void.class, true);
    }

    /**
     * Read this before prompting for notification permission: {@code enabled: false} means the
     * deployment has no VAPID keypair, and a browser only gives you one prompt.
     */
    public PushConfig pushConfig() {
        return request("GET", "/portal/push/config", null, PushConfig.class, false);
    }

    public PushSubscription pushSubscribe(PushSubscriptionInput subscription) {
        return request("POST", "/portal/push/subscriptions", subscription.toJson(),
                PushSubscription.class, false);
    }

    /**
     * Endpoint only — by the time a browser fires {@code pushsubscriptionchange} it has already
     * discarded the keys, so requiring them would break the case this exists for.
     */
    public void pushUnsubscribe(String endpoint) {
        request("DELETE", "/portal/push/subscriptions", Json.object("endpoint", endpoint),
                Void.class, true);
    }

    /**
     * <strong>Experimental — voice is not production-ready.</strong> This surface may change or
     * be withdrawn in a patch release; do not build on it yet.
     *
     * <p>STUN/TURN servers for a call this worker is about to join. Fetched per call rather than
     * cached: a TURN credential is short-lived, and a stale one fails at the point where the call
     * is already ringing. Throws {@link FivexerApiException} with code {@code voice_disabled}
     * (404) on a workspace with voice switched off — a configuration fact, not an empty relay
     * list to proceed on.
     */
    public VoiceIceServers voiceIce() {
        return request("GET", "/portal/voice/ice", null, VoiceIceServers.class, false);
    }

    /** Files on this worker's own tasks. */
    public WorkerAttachments attachments() { return attachmentsResource; }

    // ---- internals ----

    /**
     * PUT attachment bytes straight to object storage. Not a {@code /v1} call: no session token,
     * no retry. The presigned URL carries its own authorisation, and sending the worker's token
     * to a third-party storage host would leak it.
     */
    void putBytes(AttachmentUpload upload, byte[] payload) {
        okhttp3.Request.Builder rb = new okhttp3.Request.Builder()
                .url(upload.getUrl())
                .method(upload.getMethod() == null ? "PUT" : upload.getMethod(),
                        okhttp3.RequestBody.create(payload));
        if (upload.getHeaders() != null) {
            for (Map.Entry<String, String> e : upload.getHeaders().entrySet()) {
                rb.header(e.getKey(), e.getValue());
            }
        }
        int status;
        try (okhttp3.Response response = httpClient.newCall(rb.build()).execute()) {
            status = response.code();
        } catch (java.io.IOException e) {
            throw new FivexerException("attachment upload failed: " + e.getMessage(), e);
        }
        if (status >= 400) {
            throw new FivexerApiException(status, "upload_failed",
                    "storage upload failed with http " + status, null, null);
        }
    }

    /**
     * Resolve the worker to act as. Refusing locally rather than guessing is the point: a
     * wrong id here would let one worker act on another's queue.
     */
    private String require(String explicit) {
        String resolved = explicit != null ? explicit : workerId;
        if (resolved == null || resolved.isEmpty()) {
            throw new FivexerApiException(400, "worker_id_required",
                    "workerId is required — call login() first or pass it explicitly", null, null);
        }
        return resolved;
    }

    <T> T request(String method, String path, String body, Class<T> type, boolean voidExpected) {
        return request(method, path, body, null, type, voidExpected);
    }

    /**
     * No worker-plane operation needed a query string until the self-service surface arrived, so
     * the helper above had no way to send one. Building the URL here keeps every portal call on
     * one transport rather than growing a second path for two endpoints.
     */
    private <T> T request(String method, String path, String body, Map<String, String> query,
            Class<T> type, boolean voidExpected) {
        int attempt = 0;
        while (true) {
            okhttp3.HttpUrl.Builder url = okhttp3.HttpUrl.get(baseUrl + "/v1" + path).newBuilder();
            if (query != null) {
                for (Map.Entry<String, String> entry : query.entrySet()) {
                    url.addQueryParameter(entry.getKey(), entry.getValue());
                }
            }
            Request.Builder rb = new Request.Builder()
                    .url(url.build())
                    .header("Accept", "application/json");
            // The login call runs before a token exists; sending an empty bearer would be
            // a malformed request rather than an anonymous one.
            if (token != null) {
                rb.header("Authorization", "Bearer " + token);
            }
            if (body != null) {
                rb.method(method, RequestBody.create(body, Fivexer.JSON_TYPE));
                rb.header("Content-Type", "application/json");
            } else {
                rb.method(method, Fivexer.emptyBodyIfRequired(method));
            }

            int status;
            String rawBody;
            String retryAfter;
            try (Response response = httpClient.newCall(rb.build()).execute()) {
                status = response.code();
                retryAfter = response.header("retry-after");
                // Reads only: portal actions carry no idempotency key, so a replayed accept
                // could claim a task twice.
                if (attempt < maxRetries && isRetryableRead(method, status)) {
                    Double wait = Fivexer.retryAfterSeconds(retryAfter);
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
                throw toApiException(status, rawBody, Fivexer.retryAfterSeconds(retryAfter));
            }
            if (status == 204 || voidExpected) {
                return null;
            }
            return GSON.fromJson(rawBody, type);
        }
    }

    private static boolean isRetryableRead(String method, int status) {
        return "GET".equals(method) && (status == 429 || status >= 500);
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

    private FivexerApiException toApiException(int status, String rawBody, Double retryAfter) {
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
            // A non-JSON body still yields a structured exception.
        }
        return new FivexerApiException(status, code, message, null, retryAfter);
    }
}
