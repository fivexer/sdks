package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonObject;
import io.fivexer.sdk.model.ArchivedTask;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.SlotBooking;
import io.fivexer.sdk.model.SlotBookingResult;
import io.fivexer.sdk.model.SlotCandidate;
import io.fivexer.sdk.model.Task;
import io.fivexer.sdk.model.TaskHistoryPage;
import io.fivexer.sdk.model.TaskReference;
import io.fivexer.sdk.model.TimeSlot;
import io.fivexer.sdk.model.UpdateTaskInput;
import io.fivexer.sdk.model.UpdateTaskResult;
import java.util.List;
import java.util.Map;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Editing a live task, reading the finished-task archive, and appointment slots with their
 * bookings.
 */
class TaskEditingAndBookingTest extends MockServerBase {

    // ─── tasks.update ───

    @Test
    void an_update_sends_only_the_fields_that_were_set() throws Exception {
        enqueueJson(200, "{\"id\":\"task_1\",\"status\":\"accepted\",\"tags\":[\"billing\"],"
                + "\"priority\":80,\"requeued\":false,\"previousWorkerId\":null}");

        UpdateTaskResult result = client().tasks().update("task_1", new UpdateTaskInput()
                .title("Refund, second attempt")
                .priority(80));

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        assertEquals("/v1/tasks/task_1", request.getPath());
        JsonObject body = bodyOf(request);
        assertEquals(2, body.size());
        assertEquals("Refund, second attempt", body.get("title").getAsString());
        assertEquals(80, body.get("priority").getAsDouble());
        assertEquals("task_1", result.getId());
        assertEquals("accepted", result.getStatus());
        assertEquals(List.of("billing"), result.getTags());
        assertEquals(80.0, result.getPriority());
        assertFalse(result.isRequeued());
        assertNull(result.getPreviousWorkerId());
    }

    @Test
    void clearing_a_field_sends_an_explicit_null_rather_than_omitting_it() throws Exception {
        enqueueJson(200, "{\"id\":\"task_1\",\"status\":\"queued\",\"tags\":[\"a\"],\"priority\":null,"
                + "\"requeued\":false,\"previousWorkerId\":null}");

        // Omitted means "leave it alone"; only an explicit null clears — so a null argument
        // could never have said this.
        client().tasks().update("task_1", new UpdateTaskInput()
                .clearTitle().clearDescription().clearContext().clearReferences().clearMeta());

        JsonObject body = bodyOf(takeRequest());
        assertEquals(5, body.size());
        for (String field : List.of("title", "description", "context", "references", "meta")) {
            assertTrue(body.get(field).isJsonNull(), field);
        }
    }

    @Test
    void a_setter_after_a_clear_wins_and_vice_versa() throws Exception {
        enqueueJson(200, "{\"id\":\"task_1\",\"status\":\"queued\",\"tags\":[\"a\"],\"requeued\":false}");

        UpdateTaskInput input = new UpdateTaskInput()
                .clearTitle().title("Kept")
                .clearDescription().description("Described")
                .clearContext().context(Map.of("orderId", "o_1"))
                .clearReferences().references(List.of(new TaskReference("https://x.test/o/1")))
                .clearMeta().meta(Map.of("source", "crm"))
                .tags(List.of("a"));
        client().tasks().update("task_1", input);

        JsonObject body = bodyOf(takeRequest());
        assertEquals("Kept", body.get("title").getAsString());
        assertEquals("Described", body.get("description").getAsString());
        assertEquals("o_1", body.getAsJsonObject("context").get("orderId").getAsString());
        assertEquals("https://x.test/o/1",
                body.getAsJsonArray("references").get(0).getAsJsonObject().get("url").getAsString());
        assertEquals("crm", body.getAsJsonObject("meta").get("source").getAsString());
        assertEquals("Kept", input.getTitle());
        assertEquals("Described", input.getDescription());
        assertEquals("o_1", input.getContext().get("orderId"));
        assertEquals(1, input.getReferences().size());
        assertEquals("crm", input.getMeta().get("source"));
        assertEquals(List.of("a"), input.getTags());
        assertNull(input.getPriority());
    }

    @Test
    void a_retag_that_no_longer_reaches_the_holder_reports_the_requeue() throws Exception {
        enqueueJson(200, "{\"id\":\"task_1\",\"status\":\"queued\",\"tags\":[\"german\"],\"priority\":50,"
                + "\"requeued\":true,\"previousWorkerId\":\"agent_1\"}");

        UpdateTaskResult result = client().tasks().update("task_1",
                new UpdateTaskInput().tags(List.of("german")));

        assertEquals("[\"german\"]", bodyOf(takeRequest()).get("tags").toString());
        // Without this the retag that moved the work would look like a silent no-op.
        assertTrue(result.isRequeued());
        assertEquals("agent_1", result.getPreviousWorkerId());
        assertEquals("queued", result.getStatus());
    }

    // ─── tasks.history ───

    @Test
    void history_joins_several_statuses_into_one_parameter_and_parses_the_page() throws Exception {
        enqueueJson(200, "{\"tasks\":[{\"id\":\"t_9\",\"tags\":[\"ops\"],\"priority\":10,"
                + "\"status\":\"completed\",\"workerId\":\"agent_1\",\"createdAt\":1000,\"matchedAt\":2000,"
                + "\"terminalAt\":3000,\"meta\":{\"k\":\"v\"},\"title\":\"Done\",\"result\":{\"ok\":true},"
                + "\"data\":{\"hasContext\":true,\"referenceCount\":1,\"attachmentCount\":0,\"commentCount\":2},"
                + "\"archived\":true}],\"nextCursor\":\"c_2\",\"hasMore\":true}");

        TaskHistoryPage page = client().tasks().history(new TaskHistoryQuery()
                .status("completed", "failed").workerId("agent_1").tag("ops")
                .from("2026-09-01T00:00:00Z").to("2026-09-02T00:00:00Z").q("refund")
                .cursor("c_1").limit(20));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/history", pathOf(request));
        assertEquals("status=completed%2Cfailed&workerId=agent_1&tag=ops&from=2026-09-01T00%3A00%3A00Z"
                + "&to=2026-09-02T00%3A00%3A00Z&q=refund&cursor=c_1&limit=20", queryOf(request));
        assertEquals("c_2", page.getNextCursor());
        assertTrue(page.isHasMore());
        ArchivedTask task = page.getTasks().get(0);
        assertEquals("t_9", task.getId());
        assertEquals(List.of("ops"), task.getTags());
        assertEquals(10.0, task.getPriority());
        assertEquals("completed", task.getStatus());
        assertEquals("agent_1", task.getWorkerId());
        assertEquals(1000L, task.getCreatedAt());
        assertEquals(2000L, task.getMatchedAt());
        assertEquals(3000L, task.getTerminalAt());
        assertEquals("v", task.getMeta().get("k"));
        assertEquals("Done", task.getTitle());
        assertEquals(true, task.getResult().get("ok"));
        assertEquals(2, task.getData().getCommentCount());
        assertTrue(task.isArchived());
    }

    @Test
    void history_without_filters_sends_no_query_and_a_task_never_matched_reads_as_null() throws Exception {
        enqueueJson(200, "{\"tasks\":[{\"id\":\"t_1\",\"tags\":[],\"priority\":null,\"status\":\"cancelled\","
                + "\"workerId\":null,\"createdAt\":null,\"matchedAt\":null,\"terminalAt\":5,\"meta\":null,"
                + "\"title\":null,\"result\":null,\"data\":null,\"archived\":true}],"
                + "\"nextCursor\":null,\"hasMore\":false}");

        TaskHistoryPage page = client().tasks().history();

        assertEquals("", queryOf(takeRequest()));
        assertNull(page.getNextCursor());
        assertFalse(page.isHasMore());
        assertNull(page.getTasks().get(0).getMatchedAt());
        assertNull(page.getTasks().get(0).getWorkerId());
    }

    @Test
    void an_empty_status_list_omits_the_parameter_and_the_query_reads_back() throws Exception {
        enqueueJson(200, "{\"tasks\":[],\"nextCursor\":null,\"hasMore\":false}");

        TaskHistoryQuery query = new TaskHistoryQuery().status(List.of()).limit(5);
        client().tasks().history(query);

        assertEquals("limit=5", queryOf(takeRequest()));
        assertTrue(query.getStatus().isEmpty());
        assertEquals(5, query.getLimit());
        assertNull(query.getWorkerId());
        assertNull(query.getTag());
        assertNull(query.getFrom());
        assertNull(query.getTo());
        assertNull(query.getQ());
        assertNull(query.getCursor());
    }

    // ─── slots on tasks ───

    @Test
    void a_slotted_task_sends_its_appointment_on_create() throws Exception {
        enqueueJson(202, "{\"id\":\"t_1\",\"status\":\"queued\"}");

        TimeSlot slot = new TimeSlot(1_760_000_000_000L, 3_600_000L)
                .bookAheadMs(86_400_000L).onUnbooked("park");
        CreateTask input = new CreateTask(List.of("plumbing")).slot(slot);
        client().tasks().create(input);

        JsonObject sent = bodyOf(takeRequest()).getAsJsonObject("slot");
        assertEquals(1_760_000_000_000L, sent.get("startAt").getAsLong());
        assertEquals(3_600_000L, sent.get("durationMs").getAsLong());
        assertEquals(86_400_000L, sent.get("bookAheadMs").getAsLong());
        assertEquals("park", sent.get("onUnbooked").getAsString());
        assertEquals(slot, input.getSlot());
    }

    @Test
    void a_minimal_slot_omits_the_optional_fields() throws Exception {
        enqueueJson(202, "{\"id\":\"t_1\",\"status\":\"queued\"}");

        client().tasks().create(new CreateTask(List.of("x")).slot(new TimeSlot(10L, 60_000L)));

        JsonObject sent = bodyOf(takeRequest()).getAsJsonObject("slot");
        assertEquals(2, sent.size());
    }

    @Test
    void a_task_read_carries_its_slot_and_booking() throws Exception {
        enqueueJson(200, "{\"id\":\"t_1\",\"tags\":[\"x\"],\"status\":\"scheduled\","
                + "\"slot\":{\"startAt\":100,\"durationMs\":60000,\"bookAheadMs\":5,\"onUnbooked\":\"drop\"},"
                + "\"booking\":{\"taskId\":\"t_1\",\"workerId\":\"agent_1\",\"startAt\":100,\"endAt\":60100,"
                + "\"bookedAt\":50,\"source\":\"sweep\",\"warnings\":[{\"from\":90,\"to\":200,"
                + "\"reason\":\"outside_shift\"}]}}");

        Task task = client().tasks().get("t_1");

        assertEquals(100L, task.getSlot().getStartAt());
        assertEquals(60_000L, task.getSlot().getDurationMs());
        assertEquals(5L, task.getSlot().getBookAheadMs());
        assertEquals("drop", task.getSlot().getOnUnbooked());
        SlotBooking booking = task.getBooking();
        assertEquals("t_1", booking.getTaskId());
        assertEquals("agent_1", booking.getWorkerId());
        assertEquals(100L, booking.getStartAt());
        assertEquals(60_100L, booking.getEndAt());
        assertEquals(50L, booking.getBookedAt());
        assertEquals("sweep", booking.getSource());
        assertEquals(90L, booking.getWarnings().get(0).getFrom());
        assertEquals(200L, booking.getWarnings().get(0).getTo());
        assertEquals("outside_shift", booking.getWarnings().get(0).getReason());
    }

    @Test
    void an_ordinary_task_reads_no_slot_or_booking() throws Exception {
        enqueueJson(200, "{\"id\":\"t_1\",\"tags\":[\"x\"],\"status\":\"queued\"}");

        Task task = client().tasks().get("t_1");

        assertNull(task.getSlot());
        assertNull(task.getBooking());
    }

    // ─── tasks.bookings ───

    @Test
    void bookings_send_the_window_as_epoch_ms_and_unwrap_the_list() throws Exception {
        enqueueJson(200, "{\"bookings\":[{\"taskId\":\"t_1\",\"workerId\":\"agent_1\",\"startAt\":100,"
                + "\"endAt\":200,\"bookedAt\":10,\"source\":\"manual\"}],\"count\":1}");

        List<SlotBooking> bookings = client().tasks().bookings(100L, 900L, "agent_1");

        RecordedRequest request = takeRequest();
        assertEquals("GET", request.getMethod());
        assertEquals("/v1/tasks/bookings", pathOf(request));
        assertEquals("from=100&to=900&workerId=agent_1", queryOf(request));
        assertEquals(1, bookings.size());
        assertEquals("manual", bookings.get(0).getSource());
        assertNull(bookings.get(0).getWarnings());
    }

    @Test
    void bookings_for_everyone_omit_the_worker_and_an_empty_envelope_is_an_empty_list() throws Exception {
        enqueueJson(200, "{\"count\":0}");

        assertTrue(client().tasks().bookings(1L, 2L).isEmpty());
        assertEquals("from=1&to=2", queryOf(takeRequest()));
    }

    // ─── tasks.booking ───

    @Test
    void booking_candidates_are_ranked_with_their_blockers() throws Exception {
        enqueueJson(200, "{\"candidates\":[{\"workerId\":\"agent_1\",\"score\":0.9,\"effectivePriority\":95.5,"
                + "\"bookable\":false,\"reasons\":[{\"kind\":\"eligible\"}],\"clashingTaskId\":\"t_0\","
                + "\"blocked\":[{\"from\":1,\"to\":2,\"reason\":\"leave\"}],"
                + "\"warnings\":[{\"from\":3,\"to\":4,\"reason\":\"calendar\"}]}],\"count\":1}");

        List<SlotCandidate> candidates = client().tasks().booking().candidates("t/1");

        RecordedRequest request = takeRequest();
        assertEquals("GET", request.getMethod());
        assertEquals("/v1/tasks/t%2F1/booking/candidates", request.getPath());
        SlotCandidate candidate = candidates.get(0);
        assertEquals("agent_1", candidate.getWorkerId());
        assertEquals(0.9, candidate.getScore());
        assertEquals(95.5, candidate.getEffectivePriority());
        assertFalse(candidate.isBookable());
        assertEquals("eligible", candidate.getReasons().get(0).get("kind"));
        assertEquals("t_0", candidate.getClashingTaskId());
        assertEquals("leave", candidate.getBlocked().get(0).getReason());
        assertEquals("calendar", candidate.getWarnings().get(0).getReason());
    }

    @Test
    void no_candidates_reads_as_an_empty_list() throws Exception {
        enqueueJson(200, "{\"count\":0}");

        assertTrue(client().tasks().booking().candidates("t_1").isEmpty());
    }

    @Test
    void booking_a_slot_sends_the_worker_and_force_only_when_asked() throws Exception {
        String reply = "{\"id\":\"t_1\",\"booking\":{\"taskId\":\"t_1\",\"workerId\":\"agent_2\","
                + "\"startAt\":1,\"endAt\":2,\"bookedAt\":0,\"source\":\"manual\"}}";
        enqueueJson(200, reply);
        enqueueJson(200, reply);

        SlotBookingResult result = client().tasks().booking().set("t_1", "agent_2");
        client().tasks().booking().set("t_1", "agent_2", true);

        RecordedRequest first = takeRequest();
        assertEquals("POST", first.getMethod());
        assertEquals("/v1/tasks/t_1/booking", first.getPath());
        assertEquals("{\"workerId\":\"agent_2\"}", first.getBody().readUtf8());
        assertTrue(bodyOf(takeRequest()).get("force").getAsBoolean());
        assertEquals("t_1", result.getId());
        assertEquals("agent_2", result.getBooking().getWorkerId());
    }

    @Test
    void a_clashing_booking_raises_the_409() throws Exception {
        enqueueJson(409, "{\"error\":{\"code\":\"booking_refused\",\"message\":\"clash\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().tasks().booking().set("t_1", "agent_2"));

        assertEquals(409, error.getStatusCode());
        assertEquals("booking_refused", error.getCode());
    }

    @Test
    void releasing_a_booking_is_a_delete_with_no_body() throws Exception {
        enqueueEmpty(204);

        client().tasks().booking().release("t_1");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("/v1/tasks/t_1/booking", request.getPath());
    }
}
