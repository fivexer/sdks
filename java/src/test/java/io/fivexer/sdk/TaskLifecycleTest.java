package io.fivexer.sdk;

import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.Task;
import io.fivexer.sdk.model.TaskAction;
import io.fivexer.sdk.model.TaskPage;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Task lifecycle: creating, reading, listing, cancelling, and transitioning tasks.
 * Black-box: drive {@code client.tasks().*} and assert the observable result plus the exact
 * HTTP recorded by the server.
 */
class TaskLifecycleTest extends MockServerBase {

    @Test
    void creating_a_task_with_tags_returns_a_queued_task() throws Exception {
        enqueueJson(202, "{\"id\":\"task_8fk2\",\"status\":\"queued\"}");

        Task task = client().tasks().create(new CreateTask(List.of("english", "billing")));

        assertEquals("task_8fk2", task.getId());
        assertEquals("queued", task.getStatus());

        RecordedRequest req = takeRequest();
        assertEquals("POST", req.getMethod());
        assertEquals("/v1/tasks", req.getPath());
        assertEquals("Bearer sk_test_abc123", req.getHeader("Authorization"));
        assertEquals("{\"tags\":[\"english\",\"billing\"]}", req.getBody().readUtf8());
    }

    @Test
    void creating_a_task_auto_attaches_an_idempotency_key() throws Exception {
        enqueueJson(202, "{\"id\":\"task_8fk2\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("english")));

        RecordedRequest req = takeRequest();
        String key = req.getHeader("Idempotency-Key");
        assertNotNull(key);
        assertTrue(key.matches("[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}"),
                "idempotency key should be a UUID");
    }

    @Test
    void creating_a_task_with_priority_meta_and_vetoes_serializes_every_field() throws Exception {
        enqueueJson(202, "{\"id\":\"task_9\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("english"))
                .id("task_9").priority(90).meta(Map.of("ticketId", "T-441"))
                .vetoedWorkers(List.of("w_123")).skillThresholds(Map.of("english", 5.0)));

        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"id\":\"task_9\""));
        assertTrue(body.contains("\"priority\":90"));
        assertTrue(body.contains("\"vetoedWorkers\":[\"w_123\"]"));
        assertTrue(body.contains("\"skillThresholds\":{\"english\":5"));
        assertTrue(body.contains("\"meta\":{\"ticketId\":\"T-441\"}"));
    }

    @Test
    void creating_a_task_rejects_empty_tags() {
        assertThrows(IllegalArgumentException.class, () -> new CreateTask(List.of()));
        assertThrows(IllegalArgumentException.class, () -> new CreateTask(null));
    }

    @Test
    void getting_a_task_returns_parsed_fields() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"tags\":[\"english\",\"billing\"],\"priority\":90,"
                + "\"status\":\"pending\",\"workerId\":\"agent_1\",\"createdAt\":1750000000000,"
                + "\"meta\":{\"ticketId\":\"T-441\"}}");

        Task task = client().tasks().get("task_8fk2");

        assertEquals("task_8fk2", task.getId());
        assertEquals(List.of("english", "billing"), task.getTags());
        assertEquals(90.0, task.getPriority());
        assertEquals("pending", task.getStatus());
        assertEquals("agent_1", task.getWorkerId());
        assertEquals(1750000000000L, task.getCreatedAt());
        assertEquals("T-441", task.getMeta().get("ticketId"));
        assertEquals("/v1/tasks/task_8fk2", takeRequest().getPath());
    }

    @Test
    void getting_a_missing_task_raises_not_found() {
        enqueueJson(404, "{\"error\":{\"code\":\"not_found\",\"message\":\"task not found\"}}");

        FivexerApiException ex = assertThrows(FivexerApiException.class, () -> client().tasks().get("missing"));
        assertEquals(404, ex.getStatusCode());
        assertEquals("not_found", ex.getCode());
        assertEquals("task not found", ex.getMessage());
    }

    @Test
    void listing_tasks_passes_status_cursor_and_limit_as_query() throws Exception {
        enqueueJson(200, "{\"tasks\":[],\"nextCursor\":null,\"hasMore\":false}");

        client().tasks().list(new ListTasksQuery().status("queued").cursor("c1").limit(50));

        assertEquals("/v1/tasks?status=queued&cursor=c1&limit=50", takeRequest().getPath());
    }

    @Test
    void listing_tasks_paginates_and_parses_cursor() {
        enqueueJson(200, "{\"tasks\":[{\"id\":\"t1\",\"tags\":[\"x\"],\"priority\":1,\"status\":\"queued\","
                + "\"workerId\":null,\"createdAt\":1,\"meta\":null}],\"nextCursor\":\"cursor_1\",\"hasMore\":true}");

        TaskPage page = client().tasks().list(null);

        assertTrue(page.isHasMore());
        assertEquals("cursor_1", page.getNextCursor());
        assertEquals("t1", page.getTasks().get(0).getId());
    }

    @Test
    void cancelling_a_task_returns_void_and_hits_delete() throws Exception {
        enqueueEmpty(204);

        client().tasks().cancel("task_8fk2");

        RecordedRequest req = takeRequest();
        assertEquals("DELETE", req.getMethod());
        assertEquals("/v1/tasks/task_8fk2", req.getPath());
    }

    @Test
    void a_worker_accepts_a_pending_task() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"accepted\"}");

        TaskAction result = client().tasks().accept("task_8fk2", "agent_1");

        assertEquals("accepted", result.getStatus());
        assertEquals("task_8fk2", result.getId());
        RecordedRequest req = takeRequest();
        assertEquals("/v1/tasks/task_8fk2/accept", req.getPath());
        assertEquals("{\"workerId\":\"agent_1\"}", req.getBody().readUtf8());
    }

    @Test
    void a_worker_rejects_a_task_so_it_is_requeued() {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"queued\"}");

        TaskAction result = client().tasks().reject("task_8fk2", "agent_1");

        assertEquals("queued", result.getStatus());
    }

    @Test
    void a_worker_completes_a_task_with_a_result() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"completed\"}");

        TaskAction result = client().tasks().complete("task_8fk2", "agent_1", Map.of("resolved", true));

        assertEquals("completed", result.getStatus());
        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"workerId\":\"agent_1\""));
        assertTrue(body.contains("\"resolved\":true"));
    }

    @Test
    void completing_without_a_result_omits_the_field() throws Exception {
        enqueueJson(200, "{\"id\":\"task_8fk2\",\"status\":\"completed\"}");

        client().tasks().complete("task_8fk2", "agent_1", null);

        assertEquals("{\"workerId\":\"agent_1\"}", takeRequest().getBody().readUtf8());
    }
}
