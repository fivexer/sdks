package io.fivexer.sdk;

import okhttp3.OkHttpClient;
import okhttp3.Request;
import okhttp3.Response;
import okhttp3.mockwebserver.MockResponse;
import okhttp3.mockwebserver.MockWebServer;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.AfterEach;
import org.junit.jupiter.api.BeforeAll;
import org.junit.jupiter.api.BeforeEach;
import org.junit.jupiter.api.TestInstance;

import java.io.IOException;

/**
 * Shared base: spins up a MockWebServer and builds a Fivexer client pointed at it.
 * Black-box — tests drive the public SDK API and inspect the exact HTTP recorded by the
 * server (method, path, headers, body) since that wire shape *is* the /v1 contract.
 *
 * <p>A one-time JVM warmup (OkHttp/Gson/Kotlin classloading + JIT) runs in {@link BeforeAll}
 * so it is not charged against any single test — otherwise the first HTTP test in the suite
 * pays ~0.3s of cold-start and breaches the per-test budget.
 */
@TestInstance(TestInstance.Lifecycle.PER_CLASS)
abstract class MockServerBase {

    private static volatile boolean warmed = false;

    MockWebServer server;

    @BeforeAll
    void warmupJvm() throws IOException {
        // Runs once per class, but the guard makes the actual work run only once globally.
        if (warmed) {
            return;
        }
        warmed = true;
        MockWebServer ws = new MockWebServer();
        ws.start();
        ws.enqueue(new MockResponse().setBody("{\"id\":\"warm\",\"status\":\"queued\"}"));
        OkHttpClient c = new OkHttpClient();
        try (Response r = c.newCall(new Request.Builder().url(ws.url("/v1/tasks").toString()).build()).execute()) {
            new com.google.gson.Gson().fromJson(r.body() != null ? r.body().string() : "{}", java.util.Map.class);
        }
        ws.shutdown();
    }

    @BeforeEach
    void startServer() throws IOException {
        server = new MockWebServer();
        server.start();
    }

    @AfterEach
    void stopServer() throws IOException {
        server.shutdown();
    }

    Fivexer client() {
        return new Fivexer(server.url("/").toString(), "sk_test_abc123", 0);
    }

    /** A worker-portal client already holding a session token, as if login() had run. */
    FivexerWorker worker() {
        return new FivexerWorker(server.url("/").toString(), "wt_s3ss10n", "agent_1", null, 0);
    }

    /** A worker-portal client with no token yet — the pre-login state. */
    FivexerWorker anonymousWorker() {
        return new FivexerWorker(server.url("/").toString(), null, null, null, 0);
    }

    /** Parse a recorded request body back into a JSON object for assertions. */
    static com.google.gson.JsonObject bodyOf(RecordedRequest request) {
        return com.google.gson.JsonParser.parseString(request.getBody().readUtf8()).getAsJsonObject();
    }

    /** The query string of a recorded request, or "" when it had none. */
    static String queryOf(RecordedRequest request) {
        String path = request.getPath();
        int mark = path == null ? -1 : path.indexOf('?');
        return mark < 0 ? "" : path.substring(mark + 1);
    }

    /** The path of a recorded request with any query string stripped. */
    static String pathOf(RecordedRequest request) {
        String path = request.getPath();
        int mark = path == null ? -1 : path.indexOf('?');
        return mark < 0 ? path : path.substring(0, mark);
    }

    void enqueueJson(int status, String json, String... headers) {
        MockResponse response = new MockResponse().setResponseCode(status);
        if (json != null) {
            response.setBody(json);
        }
        for (int i = 0; i + 1 < headers.length; i += 2) {
            response.setHeader(headers[i], headers[i + 1]);
        }
        server.enqueue(response);
    }

    void enqueueEmpty(int status, String... headers) {
        MockResponse response = new MockResponse().setResponseCode(status);
        for (int i = 0; i + 1 < headers.length; i += 2) {
            response.setHeader(headers[i], headers[i + 1]);
        }
        server.enqueue(response);
    }

    RecordedRequest takeRequest() throws InterruptedException {
        return server.takeRequest();
    }
}
