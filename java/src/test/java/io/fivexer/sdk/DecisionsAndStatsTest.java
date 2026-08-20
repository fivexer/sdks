package io.fivexer.sdk;

import io.fivexer.sdk.model.Decision;
import io.fivexer.sdk.model.WorkspaceStats;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

import java.util.List;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Decision traces (explainability) and workspace stats.
 */
class DecisionsAndStatsTest extends MockServerBase {

    @Test
    void querying_decisions_by_task_returns_candidates_with_scores() throws Exception {
        enqueueJson(200, "{\"decisions\":[{\"id\":\"dec_1\",\"taskId\":\"task_8fk2\","
                + "\"workerId\":\"agent_1\",\"matchedAt\":1750000000000,\"mode\":\"best-match\","
                + "\"candidates\":[{\"workerId\":\"agent_1\",\"eligible\":true,\"chosen\":true,\"score\":101},"
                + "{\"workerId\":\"agent_2\",\"eligible\":false,\"chosen\":false,\"score\":0}]}]}");

        List<Decision> decisions = client().decisions()
                .list(new ListDecisionsQuery().taskId("task_8fk2"));

        assertEquals(1, decisions.size());
        Decision dec = decisions.get(0);
        assertEquals("dec_1", dec.getId());
        assertEquals("task_8fk2", dec.getTaskId());
        assertEquals("agent_1", dec.getWorkerId());
        assertEquals(1750000000000L, dec.getMatchedAt());
        assertEquals("best-match", dec.getMode());
        assertEquals(2, dec.getCandidates().size());
        // workerId is pulled out; the rest lands verbatim in detail
        assertEquals("agent_1", dec.getCandidates().get(0).getWorkerId());
        assertEquals(101.0, dec.getCandidates().get(0).getDetail().get("score"));
        assertTrue((Boolean) dec.getCandidates().get(0).getDetail().get("chosen"));

        RecordedRequest req = takeRequest();
        assertTrue(req.getPath().startsWith("/v1/decisions"));
        assertTrue(req.getPath().contains("taskId=task_8fk2"));
    }

    @Test
    void querying_decisions_by_worker_and_limit_passes_them() throws Exception {
        enqueueJson(200, "{\"decisions\":[]}");

        List<Decision> result = client().decisions()
                .list(new ListDecisionsQuery().workerId("agent_1").limit(10));

        assertTrue(result.isEmpty());
        String path = takeRequest().getPath();
        assertTrue(path.contains("workerId=agent_1"));
        assertTrue(path.contains("limit=10"));
    }

    @Test
    void decisions_default_to_an_empty_list_when_none_exist() {
        enqueueJson(200, "{\"decisions\":[]}");
        assertTrue(client().decisions().list(null).isEmpty());
    }

    @Test
    void workspace_stats_reports_queue_depth_meter_and_plan() {
        enqueueJson(200, "{\"plan\":\"pro\",\"tasks\":{\"queued\":3,\"pending\":1,\"accepted\":2},"
                + "\"workers\":5,\"meter\":{\"period\":\"2026-07\",\"matchedTasks\":421,"
                + "\"includedTasksPerMonth\":50000}}");

        WorkspaceStats stats = client().stats();

        assertEquals("pro", stats.getPlan());
        assertEquals(3, stats.getTasks().get("queued"));
        assertEquals(5, stats.getWorkers());
        assertEquals("2026-07", stats.getMeter().getPeriod());
        assertEquals(421, stats.getMeter().getMatchedTasks());
        assertEquals(50000, stats.getMeter().getIncludedTasksPerMonth());
    }
}
