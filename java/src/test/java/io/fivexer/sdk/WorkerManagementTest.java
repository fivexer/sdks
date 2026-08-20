package io.fivexer.sdk;

import io.fivexer.sdk.model.UpsertWorker;
import io.fivexer.sdk.model.WorkerList;
import io.fivexer.sdk.model.WorkerQueue;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

import java.util.List;
import java.util.Map;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Worker management: upsert, list, queue inspection, and removal.
 */
class WorkerManagementTest extends MockServerBase {

    @Test
    void upserting_a_worker_without_an_id_gets_one_assigned() throws Exception {
        enqueueJson(200, "{\"id\":\"w_abc\"}");

        String workerId = client().workers().upsert(new UpsertWorker());

        assertEquals("w_abc", workerId);
        assertEquals("{}", takeRequest().getBody().readUtf8());
    }

    @Test
    void upserting_a_worker_with_tags_and_routing_weights_serializes_them() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").tags(List.of("english", "billing"))
                .routingWeights(Map.of("english", 100.0)));

        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"id\":\"agent_1\""));
        assertTrue(body.contains("\"tags\":[\"english\",\"billing\"]"));
        assertTrue(body.contains("\"routingWeights\":{\"english\":100"));
    }

    @Test
    void upserting_a_field_worker_includes_geo_fields() throws Exception {
        enqueueJson(200, "{\"id\":\"agent_1\"}");

        client().workers().upsert(new UpsertWorker("agent_1").ip("203.0.113.5")
                .latitude(59.4).longitude(24.7).maxTravelDistanceKm(15));

        String body = takeRequest().getBody().readUtf8();
        assertTrue(body.contains("\"ip\":\"203.0.113.5\""));
        assertTrue(body.contains("\"latitude\":59.4"));
        assertTrue(body.contains("\"longitude\":24.7"));
        assertTrue(body.contains("\"maxTravelDistanceKm\":15"));
    }

    @Test
    void listing_workers_returns_ids_and_count() {
        enqueueJson(200, "{\"workers\":[\"agent_1\",\"agent_2\"],\"count\":2}");

        WorkerList result = client().workers().list();

        assertEquals(List.of("agent_1", "agent_2"), result.getWorkers());
        assertEquals(2, result.getCount());
    }

    @Test
    void inspecting_a_worker_queue_returns_their_task_ids() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"taskIds\":[\"task_8fk2\",\"task_9\"]}");

        WorkerQueue queue = client().workers().queue("agent_1");

        assertEquals("agent_1", queue.getWorkerId());
        assertEquals(List.of("task_8fk2", "task_9"), queue.getTaskIds());
        assertEquals("/v1/workers/agent_1/queue", takeRequest().getPath());
    }

    @Test
    void removing_a_worker_hits_delete() throws Exception {
        enqueueEmpty(204);

        client().workers().remove("agent_1");

        RecordedRequest req = takeRequest();
        assertEquals("DELETE", req.getMethod());
        assertEquals("/v1/workers/agent_1", req.getPath());
    }

    @Test
    void upserting_a_worker_at_the_plan_limit_raises() {
        enqueueJson(402, "{\"error\":{\"code\":\"plan_limit_exceeded\",\"message\":\"worker limit of 25 reached\"}}",
                "x-quota-workers-limit", "25", "x-quota-workers-remaining", "0");

        FivexerApiException ex = assertThrows(FivexerApiException.class,
                () -> client().workers().upsert(new UpsertWorker("agent_1")));
        assertEquals(402, ex.getStatusCode());
        assertEquals("plan_limit_exceeded", ex.getCode());
        assertEquals(25, ex.getQuota().getWorkersLimit());
        assertEquals(0, ex.getQuota().getWorkersRemaining());
    }
}
