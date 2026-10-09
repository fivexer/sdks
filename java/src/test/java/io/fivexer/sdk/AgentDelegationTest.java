package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.ResultContract;
import io.fivexer.sdk.model.TaskContext;
import io.fivexer.sdk.model.TaskOutcome;
import java.util.List;
import java.time.Duration;
import java.util.Map;
import java.util.concurrent.TimeUnit;
import okhttp3.OkHttpClient;
import okhttp3.mockwebserver.MockResponse;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Handing work off and reading it back: the result contract a task is created with, and the
 * outcome read that long-polls until the work is done.
 */
class AgentDelegationTest extends MockServerBase {

    @Test
    void a_task_carries_its_result_contract_on_the_wire() throws Exception {
        enqueueJson(202, "{\"id\":\"job-1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("seo"))
                .id("job-1")
                .resultContract(new ResultContract()
                        .schema(Map.of("type", "object", "required", List.of("url")))
                        .acceptanceCriteria("A live URL")));

        JsonObject contract = bodyOf(takeRequest()).getAsJsonObject("resultContract");
        assertEquals("object", contract.getAsJsonObject("schema").get("type").getAsString());
        assertEquals("url", contract.getAsJsonObject("schema").getAsJsonArray("required").get(0).getAsString());
        assertEquals("A live URL", contract.get("acceptanceCriteria").getAsString());
    }

    @Test
    void a_task_without_a_contract_sends_none() throws Exception {
        enqueueJson(202, "{\"id\":\"job-2\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("seo")));

        assertFalse(bodyOf(takeRequest()).has("resultContract"));
    }

    @Test
    void the_outcome_long_polls_with_wait_ms() throws Exception {
        enqueueJson(200, "{\"id\":\"job-1\",\"status\":\"completed\",\"done\":true,\"workerId\":\"w1\","
                + "\"result\":{\"url\":\"https://example.com\"},\"terminalAt\":1760000000000}");

        TaskOutcome outcome = client().tasks().outcome("job-1", 20_000);

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/job-1/outcome?waitMs=20000", request.getPath());
        assertTrue(outcome.isDone());
        assertEquals("completed", outcome.getStatus());
        assertEquals("w1", outcome.getWorkerId());
        assertEquals("https://example.com", outcome.getResult().get("url"));
        assertEquals(1760000000000L, outcome.getTerminalAt());
        assertEquals("job-1", outcome.getId());
    }

    @Test
    void a_live_outcome_reads_without_waiting() throws Exception {
        enqueueJson(200, "{\"id\":\"job/3\",\"status\":\"queued\",\"done\":false,\"workerId\":null,"
                + "\"result\":null,\"terminalAt\":null}");

        TaskOutcome outcome = client().tasks().outcome("job/3");

        assertEquals("/v1/tasks/job%2F3/outcome", takeRequest().getPath());
        assertFalse(outcome.isDone());
        assertNull(outcome.getWorkerId());
        assertNull(outcome.getResult());
        assertNull(outcome.getTerminalAt());
    }

    @Test
    void the_context_reads_back_the_contract() throws Exception {
        enqueueJson(200, "{\"taskId\":\"job-1\",\"title\":null,\"description\":null,\"context\":null,"
                + "\"references\":[],\"resultContract\":{\"schema\":{\"type\":\"object\"},"
                + "\"acceptanceCriteria\":\"A live URL\"},\"createdAt\":1,\"updatedAt\":1}");

        TaskContext context = client().tasks().context().get("job-1");

        assertEquals("object", context.getResultContract().getSchema().get("type"));
        assertEquals("A live URL", context.getResultContract().getAcceptanceCriteria());
    }

    /** A long-poll answer that arrives after a short client read timeout would have given up. */
    private void enqueueSlowOutcome() {
        server.enqueue(new MockResponse()
                .setResponseCode(200)
                .setHeader("Content-Type", "application/json")
                .setBody("{\"id\":\"job-1\",\"status\":\"completed\",\"done\":true}")
                .setHeadersDelay(400, TimeUnit.MILLISECONDS));
    }

    private Fivexer clientWithReadTimeout(Duration readTimeout) {
        OkHttpClient http = new OkHttpClient.Builder().readTimeout(readTimeout).build();
        return new Fivexer(server.url("/").toString(), "sk_test_abc123", http, 0);
    }

    @Test
    void a_long_poll_outlasts_a_read_timeout_shorter_than_its_wait() {
        Fivexer shortTimeout = clientWithReadTimeout(Duration.ofMillis(200));

        enqueueSlowOutcome();
        assertTrue(shortTimeout.tasks().outcome("job-1", 1_000).isDone());

        // The same client on an ordinary read still keeps its own, shorter timeout.
        enqueueSlowOutcome();
        assertThrows(FivexerException.class, () -> shortTimeout.tasks().outcome("job-1"));
    }

    @Test
    void a_client_with_a_long_enough_read_timeout_is_used_as_it_is() {
        enqueueSlowOutcome();
        assertTrue(clientWithReadTimeout(Duration.ofMinutes(5)).tasks().outcome("job-1", 1_000).isDone());

        enqueueSlowOutcome();
        assertTrue(clientWithReadTimeout(Duration.ZERO).tasks().outcome("job-1", 1_000).isDone());
    }
}
