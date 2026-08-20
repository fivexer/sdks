package io.fivexer.sdk;

import com.google.gson.Gson;
import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.AcceptSupervisorInvite;
import io.fivexer.sdk.model.PushConfig;
import io.fivexer.sdk.model.PushSubscriptionInput;
import io.fivexer.sdk.model.SupervisorAssignResult;
import io.fivexer.sdk.model.SupervisorAvailabilityResult;
import io.fivexer.sdk.model.SupervisorEntry;
import io.fivexer.sdk.model.SupervisorMe;
import io.fivexer.sdk.model.SupervisorOverview;
import io.fivexer.sdk.model.SupervisorPushAck;
import io.fivexer.sdk.model.SupervisorSession;
import io.fivexer.sdk.model.TaskAction;
import io.fivexer.sdk.model.TaskPriority;
import io.fivexer.sdk.model.UnparkTask;
import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.RequestBody;
import okhttp3.Response;

/**
 * Supervisor-facing client — the {@code sv_} session plane.
 *
 * <p>A third credential type alongside {@link Fivexer} (workspace {@code sk_} key) and
 * {@link FivexerWorker} (worker {@code wt_} token). A supervisor watches and unblocks work
 * rather than doing it: they can unpark a task, reprioritise it, hand it to a crew member and
 * pause one; they can never create work, manage the roster, or reach the workspace plane. Scope
 * is either one team or the whole workspace, and every action is checked against it server-side
 * — a crew lead cannot push their crew's work onto another crew, or pull another crew's in.
 *
 * <p>Unlike the worker plane there is no login and no refresh. A session begins by redeeming a
 * single-use link an owner generated in the console, and ends when it expires or is revoked.
 * There is nothing to rotate with, so an expired session means "get a new link" — this client
 * deliberately has no recovery path, because inventing one would only hide that.
 */
public class FivexerSupervisor implements AutoCloseable {

    private static final Gson GSON = new Gson();

    private final String baseUrl;
    private final OkHttpClient httpClient;
    private final int maxRetries;

    private volatile String token;
    private volatile Long expiresAt;

    public FivexerSupervisor(String baseUrl) {
        this(baseUrl, null, null, 1);
    }

    public FivexerSupervisor(String baseUrl, String token) {
        this(baseUrl, token, null, 1);
    }

    public FivexerSupervisor(String baseUrl, String token, OkHttpClient httpClient, int maxRetries) {
        if (baseUrl == null || baseUrl.isEmpty()) {
            throw new IllegalArgumentException("baseUrl is required");
        }
        this.baseUrl = baseUrl.replaceAll("/+$", "");
        this.token = token;
        this.httpClient = httpClient != null ? httpClient : new OkHttpClient();
        this.maxRetries = maxRetries;
    }

    public String getBaseUrl() { return baseUrl; }

    /** The current supervisor session token, if one has been adopted. */
    public String getSessionToken() { return token; }

    /** When the current token stops working — epoch-milliseconds, as the wire sends it. */
    public Long getSessionExpiresAt() { return expiresAt; }

    /** Adopt a supervisor token from a prior session. */
    public void setToken(String token, Long expiresAt) {
        this.token = token;
        this.expiresAt = expiresAt;
    }

    @Override
    public void close() {
        httpClient.dispatcher().executorService().shutdown();
        httpClient.connectionPool().evictAll();
    }

    // ---- session ----

    /** Where a supervisor can sign in from. Unauthenticated. */
    public SupervisorEntry entry() {
        return request("GET", "/supervisor-auth/entry", null, SupervisorEntry.class, false);
    }

    /**
     * Redeem a supervisor link, adopting the returned session.
     *
     * <p>The token is single-use, and every way of failing answers identically — see
     * {@link AcceptSupervisorInvite}.
     */
    public SupervisorSession acceptInvite(AcceptSupervisorInvite input) {
        SupervisorSession session = request("POST", "/supervisor-auth/accept", input.toJson(),
                SupervisorSession.class, false);
        setToken(session.getToken(), session.getExpiresAt());
        return session;
    }

    /**
     * End the session and forget the token.
     *
     * <p>Also drops this supervisor's push subscriptions server-side: a handed-over phone must
     * stop buzzing with another crew's work, so signing out and unsubscribing are one trust
     * boundary rather than two steps a caller can get half-right.
     */
    public void logout() {
        request("POST", "/supervisor-auth/logout", null, Void.class, true);
        setToken(null, null);
    }

    // ---- board ----

    /** The signed-in supervisor and their scope. A null {@code teamKey} means the whole workspace. */
    public SupervisorMe me() {
        return request("GET", "/supervisor/me", null, SupervisorMe.class, false);
    }

    /** The whole board in one request: counts, crew (busiest first) and parked work. */
    public SupervisorOverview overview() {
        return request("GET", "/supervisor/overview", null, SupervisorOverview.class, false);
    }

    // ---- actions ----

    /**
     * Return a parked task to the queue. Reset the clocks that parked it, or the next sweep may
     * park it straight back.
     */
    public TaskAction unpark(String taskId, UnparkTask options) {
        String body = options == null ? "{}" : options.toJson();
        return request("POST", "/supervisor/tasks/" + Json.enc(taskId) + "/unpark", body,
                TaskAction.class, false);
    }

    public TaskAction unpark(String taskId) {
        return unpark(taskId, null);
    }

    public TaskPriority setPriority(String taskId, double priority) {
        return request("POST", "/supervisor/tasks/" + Json.enc(taskId) + "/priority",
                Json.object("priority", priority), TaskPriority.class, false);
    }

    /**
     * Hand a task to a specific crew member.
     *
     * <p>Both ends are scope-checked: a task from another crew, or a worker outside this one, is
     * a 403 rather than a silent move. A refusal from the matcher (paused, backlog full, prior
     * rejection) surfaces as a 400 {@code assign_blocked}; {@code force} bypasses those checks
     * but never worker existence.
     */
    public SupervisorAssignResult assign(String taskId, String workerId, Boolean force) {
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.addProperty("workerId", workerId);
        if (force != null) {
            body.addProperty("force", force);
        }
        return request("POST", "/supervisor/tasks/" + Json.enc(taskId) + "/assign", body.toString(),
                SupervisorAssignResult.class, false);
    }

    public SupervisorAssignResult assign(String taskId, String workerId) {
        return assign(taskId, workerId, null);
    }

    /**
     * Pause or resume a crew member.
     *
     * <p>Pausing never releases work implicitly — that is the engine's rule. {@code
     * releaseBacklog} is the explicit redistribution move, and only <em>pending</em> work moves;
     * anything already accepted stays with whoever accepted it.
     */
    public SupervisorAvailabilityResult setAvailability(String workerId, boolean available,
            Boolean releaseBacklog) {
        com.google.gson.JsonObject body = new com.google.gson.JsonObject();
        body.addProperty("available", available);
        if (releaseBacklog != null) {
            body.addProperty("releaseBacklog", releaseBacklog);
        }
        return request("POST", "/supervisor/workers/" + Json.enc(workerId) + "/availability",
                body.toString(), SupervisorAvailabilityResult.class, false);
    }

    public SupervisorAvailabilityResult setAvailability(String workerId, boolean available) {
        return setAvailability(workerId, available, null);
    }

    // ---- push ----

    /**
     * Read before prompting for notification permission: {@code enabled: false} means this
     * deployment has no VAPID keypair, and a browser only gives you one prompt.
     */
    public PushConfig pushConfig() {
        return request("GET", "/supervisor/push/config", null, PushConfig.class, false);
    }

    /** Register a browser subscription against this supervisor's own table. */
    public boolean pushSubscribe(PushSubscriptionInput subscription) {
        SupervisorPushAck ack = request("POST", "/supervisor/push/subscriptions",
                subscription.toJson(), SupervisorPushAck.class, false);
        return ack != null && Boolean.TRUE.equals(ack.getOk());
    }

    /** Unregister. Endpoint only — a browser discards the keys before it tells you. */
    public void pushUnsubscribe(String endpoint) {
        request("DELETE", "/supervisor/push/subscriptions", Json.object("endpoint", endpoint),
                Void.class, true);
    }

    // ---- internals ----

    private <T> T request(String method, String path, String body, Class<T> type, boolean voidExpected) {
        int attempt = 0;
        while (true) {
            Request.Builder rb = new Request.Builder()
                    .url(baseUrl + "/v1" + path)
                    .header("Accept", "application/json");
            // `entry` and `accept` run before a token exists; an empty bearer would be a
            // malformed request rather than an anonymous one.
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
                // Reads only: a supervisor action carries no idempotency key, so a replayed
                // assign could move a task twice.
                if (attempt < maxRetries && "GET".equals(method) && isRetryableStatus(status)) {
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

    private static boolean isRetryableStatus(int status) {
        return status == 429 || status >= 500;
    }

    private static void sleepQuietly(long millis) {
        try {
            Thread.sleep(millis);
        } catch (InterruptedException e) {
            Thread.currentThread().interrupt();
        }
    }

    private static FivexerApiException toApiException(int status, String rawBody, Double retryAfter) {
        String code = "unknown_error";
        String message = "http " + status;
        try {
            com.google.gson.JsonObject parsed = com.google.gson.JsonParser.parseString(rawBody)
                    .getAsJsonObject();
            if (parsed.has("error") && parsed.get("error").isJsonObject()) {
                com.google.gson.JsonObject error = parsed.getAsJsonObject("error");
                if (error.has("code")) {
                    code = error.get("code").getAsString();
                }
                if (error.has("message")) {
                    message = error.get("message").getAsString();
                }
            }
        } catch (RuntimeException ignored) {
            // A non-JSON body (a proxy's HTML error page) leaves the defaults above, which
            // still name the status — more useful than a parse failure masking it.
        }
        return new FivexerApiException(status, code, message, null, retryAfter);
    }
}
