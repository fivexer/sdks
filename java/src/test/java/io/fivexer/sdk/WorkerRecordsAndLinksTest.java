package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.JsonArray;
import com.google.gson.JsonObject;
import io.fivexer.sdk.model.CorrectTimeEntryInput;
import io.fivexer.sdk.model.CorrectedTimeEntry;
import io.fivexer.sdk.model.CreateTimeEntryInput;
import io.fivexer.sdk.model.ExpiringSkillsResult;
import io.fivexer.sdk.model.ExpiringWorkerSkill;
import io.fivexer.sdk.model.LinkWorkerInput;
import io.fivexer.sdk.model.TeamTimeDay;
import io.fivexer.sdk.model.TeamTimeResult;
import io.fivexer.sdk.model.TeamTimeWorker;
import io.fivexer.sdk.model.TimeCorrection;
import io.fivexer.sdk.model.TimeCorrectionList;
import io.fivexer.sdk.model.TimeEntryBreak;
import io.fivexer.sdk.model.TimeEntryCorrectionResult;
import io.fivexer.sdk.model.WorkerLink;
import io.fivexer.sdk.model.WorkerMetricsToday;
import io.fivexer.sdk.model.WorkerOffboardingSummary;
import io.fivexer.sdk.model.WorkerTimeEntriesResult;
import io.fivexer.sdk.model.WorkerTimeEntry;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Payroll-grade working time (adding and correcting records, the correction trail, the team
 * report), the offboarding preview, connector links, and expiring qualifications.
 */
class WorkerRecordsAndLinksTest extends MockServerBase {

    private static final String CORRECTION_RESULT = "{\"entry\":{\"id\":\"s_1\",\"type\":\"shift\","
            + "\"startedAt\":\"2026-09-01T08:00:00Z\",\"endedAt\":null,\"corrected\":true,\"source\":\"operator\","
            + "\"endReason\":null,\"reason\":null,\"description\":\"Stocktake\",\"taskId\":\"t_1\"},"
            + "\"correction\":{\"id\":\"c_1\",\"entryType\":\"shift\",\"entryId\":\"s_1\",\"workerId\":\"agent_1\","
            + "\"before\":{\"endedAt\":\"2026-09-01T12:00:00Z\"},\"after\":{\"endedAt\":null},"
            + "\"note\":\"kept working\",\"by\":\"Mari\",\"at\":\"2026-09-02T09:00:00Z\"},"
            + "\"breaks\":[{\"id\":\"b_1\",\"type\":\"break\",\"startedAt\":\"2026-09-01T10:00:00Z\","
            + "\"endedAt\":\"2026-09-01T10:15:00Z\",\"corrected\":true,\"reason\":\"lunch\"}],"
            + "\"corrections\":[{\"id\":\"c_1\"},{\"id\":\"c_2\",\"entryType\":\"break\",\"before\":null}]}";

    // ─── workers.createTimeEntry ───

    @Test
    void adding_a_missed_shift_sends_both_ends_the_reason_and_its_breaks() throws Exception {
        enqueueJson(201, CORRECTION_RESULT);

        CreateTimeEntryInput input = new CreateTimeEntryInput("2026-09-01T08:00:00Z",
                "2026-09-01T16:00:00Z", "forgot to clock in")
                .breaks(List.of(new TimeEntryBreak("2026-09-01T12:00:00Z", "2026-09-01T12:30:00Z")))
                .description("Stocktake").taskId("t_1");
        TimeEntryCorrectionResult result = client().workers().createTimeEntry("agent_1", input);

        RecordedRequest request = takeRequest();
        assertEquals("POST", request.getMethod());
        assertEquals("/v1/workers/agent_1/time-entries", request.getPath());
        JsonObject body = bodyOf(request);
        assertEquals("2026-09-01T08:00:00Z", body.get("startedAt").getAsString());
        assertEquals("2026-09-01T16:00:00Z", body.get("endedAt").getAsString());
        assertEquals("forgot to clock in", body.get("note").getAsString());
        assertEquals("Stocktake", body.get("description").getAsString());
        assertEquals("t_1", body.get("taskId").getAsString());
        JsonObject brk = body.getAsJsonArray("breaks").get(0).getAsJsonObject();
        assertEquals(2, brk.size());
        assertEquals("2026-09-01T12:30:00Z", brk.get("endedAt").getAsString());
        assertEquals("forgot to clock in", input.getNote());
        assertEquals("2026-09-01T08:00:00Z", input.getStartedAt());
        assertEquals("2026-09-01T16:00:00Z", input.getEndedAt());
        assertEquals(1, input.getBreaks().size());
        assertEquals("Stocktake", input.getDescription());
        assertEquals("t_1", input.getTaskId());

        CorrectedTimeEntry entry = result.getEntry();
        assertEquals("s_1", entry.getId());
        assertEquals("shift", entry.getType());
        assertEquals("2026-09-01T08:00:00Z", entry.getStartedAt());
        assertNull(entry.getEndedAt());
        assertTrue(entry.isCorrected());
        assertEquals("operator", entry.getSource());
        assertNull(entry.getEndReason());
        assertNull(entry.getReason());
        assertEquals("Stocktake", entry.getDescription());
        assertEquals("t_1", entry.getTaskId());
        TimeCorrection correction = result.getCorrection();
        assertEquals("c_1", correction.getId());
        assertEquals("shift", correction.getEntryType());
        assertEquals("s_1", correction.getEntryId());
        assertEquals("agent_1", correction.getWorkerId());
        assertEquals("2026-09-01T12:00:00Z", correction.getBefore().get("endedAt"));
        assertTrue(correction.getAfter().containsKey("endedAt"));
        assertEquals("kept working", correction.getNote());
        assertEquals("Mari", correction.getBy());
        assertEquals("2026-09-02T09:00:00Z", correction.getAt());
        assertEquals("lunch", result.getBreaks().get(0).getReason());
        assertNull(result.getCorrections().get(1).getBefore());
    }

    @Test
    void a_minimal_added_shift_omits_everything_optional() throws Exception {
        enqueueJson(201, CORRECTION_RESULT);

        client().workers().createTimeEntry("agent_1", new CreateTimeEntryInput("a", "b", "why"));

        assertEquals(3, bodyOf(takeRequest()).size());
    }

    @Test
    void an_added_shift_refuses_a_missing_end_or_reason() {
        assertThrows(IllegalArgumentException.class, () -> new CreateTimeEntryInput(null, "b", "n"));
        assertThrows(IllegalArgumentException.class, () -> new CreateTimeEntryInput("a", null, "n"));
        assertThrows(IllegalArgumentException.class, () -> new CreateTimeEntryInput("a", "b", null));
        assertThrows(IllegalArgumentException.class, () -> new TimeEntryBreak(null, "b"));
    }

    // ─── workers.correctTimeEntry ───

    @Test
    void a_correction_patches_only_what_changed_plus_the_reason() throws Exception {
        enqueueJson(200, CORRECTION_RESULT);

        CorrectTimeEntryInput input = new CorrectTimeEntryInput("clocked in late")
                .startedAt("2026-09-01T07:45:00Z").type("shift");
        client().workers().correctTimeEntry("agent_1", "s/1", input);

        RecordedRequest request = takeRequest();
        assertEquals("PATCH", request.getMethod());
        assertEquals("/v1/workers/agent_1/time-entries/s%2F1", request.getPath());
        JsonObject body = bodyOf(request);
        assertEquals(3, body.size());
        assertEquals("clocked in late", body.get("note").getAsString());
        assertEquals("2026-09-01T07:45:00Z", body.get("startedAt").getAsString());
        assertEquals("shift", body.get("type").getAsString());
        assertEquals("clocked in late", input.getNote());
        assertEquals("shift", input.getType());
        assertEquals("2026-09-01T07:45:00Z", input.getStartedAt());
        assertNull(input.getEndedAt());
        assertNull(input.getBreaks());
    }

    @Test
    void reopening_and_clearing_send_explicit_nulls_and_an_open_break_keeps_its_null_end() throws Exception {
        enqueueJson(200, CORRECTION_RESULT);

        CorrectTimeEntryInput input = new CorrectTimeEntryInput("kept working")
                .reopen().clearDescription().clearTaskId()
                .breaks(List.of(
                        new TimeEntryBreak("2026-09-01T10:00:00Z", "2026-09-01T10:15:00Z").id("b_1"),
                        new TimeEntryBreak("2026-09-01T14:00:00Z", null)));
        client().workers().correctTimeEntry("agent_1", "s_1", input);

        JsonObject body = bodyOf(takeRequest());
        assertTrue(body.get("endedAt").isJsonNull());
        assertTrue(body.get("description").isJsonNull());
        assertTrue(body.get("taskId").isJsonNull());
        JsonArray breaks = body.getAsJsonArray("breaks");
        assertEquals("b_1", breaks.get(0).getAsJsonObject().get("id").getAsString());
        JsonObject open = breaks.get(1).getAsJsonObject();
        assertFalse(open.has("id"));
        // An open break is `endedAt: null`, never an absent field.
        assertTrue(open.get("endedAt").isJsonNull());
        assertEquals("b_1", input.getBreaks().get(0).getId());
        assertEquals("2026-09-01T14:00:00Z", input.getBreaks().get(1).getStartedAt());
        assertNull(input.getBreaks().get(1).getEndedAt());
    }

    @Test
    void a_value_after_a_clear_wins() throws Exception {
        enqueueJson(200, CORRECTION_RESULT);

        CorrectTimeEntryInput input = new CorrectTimeEntryInput("fix")
                .reopen().endedAt("2026-09-01T17:00:00Z")
                .clearDescription().description("Night shift")
                .clearTaskId().taskId("t_2");
        client().workers().correctTimeEntry("agent_1", "s_1", input);

        JsonObject body = bodyOf(takeRequest());
        assertEquals("2026-09-01T17:00:00Z", body.get("endedAt").getAsString());
        assertEquals("Night shift", body.get("description").getAsString());
        assertEquals("t_2", body.get("taskId").getAsString());
        assertEquals("Night shift", input.getDescription());
        assertEquals("t_2", input.getTaskId());
    }

    @Test
    void a_correction_without_a_reason_is_refused_before_the_wire() {
        assertThrows(IllegalArgumentException.class, () -> new CorrectTimeEntryInput(null));
        assertThrows(IllegalArgumentException.class, () -> new CorrectTimeEntryInput(""));
    }

    @Test
    void a_signed_off_period_refuses_the_correction() throws Exception {
        enqueueJson(409, "{\"error\":{\"code\":\"period_closed\",\"message\":\"closed\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().workers().correctTimeEntry("agent_1", "s_1", new CorrectTimeEntryInput("x")));

        assertEquals("period_closed", error.getCode());
    }

    // ─── workers.timeCorrections ───

    @Test
    void the_correction_trail_is_read_per_worker() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"corrections\":[{\"id\":\"c_1\",\"entryType\":\"break\","
                + "\"entryId\":\"b_1\",\"workerId\":\"agent_1\",\"before\":null,\"after\":{\"startedAt\":\"x\"},"
                + "\"note\":\"added\",\"by\":null,\"at\":\"2026-09-02T09:00:00Z\"}]}");

        TimeCorrectionList trail = client().workers().timeCorrections("agent_1");

        RecordedRequest request = takeRequest();
        assertEquals("GET", request.getMethod());
        assertEquals("/v1/workers/agent_1/time-corrections", request.getPath());
        assertEquals("agent_1", trail.getWorkerId());
        assertNull(trail.getCorrections().get(0).getBefore());
        assertNull(trail.getCorrections().get(0).getBy());
    }

    // ─── the time log's new fields ───

    @Test
    void time_entries_carry_their_work_note_and_task() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"from\":\"a\",\"to\":\"b\",\"entries\":["
                + "{\"id\":\"s_1\",\"type\":\"shift\",\"startedAt\":\"a\",\"endedAt\":null,\"durationMs\":0,"
                + "\"corrected\":false,\"description\":\"Stocktake\",\"taskId\":\"t_1\"}],\"totals\":{}}");

        WorkerTimeEntriesResult log = client().workers().timeEntries("agent_1");

        WorkerTimeEntry entry = log.getEntries().get(0);
        assertEquals("Stocktake", entry.getDescription());
        assertEquals("t_1", entry.getTaskId());
    }

    @Test
    void the_portal_today_view_reports_when_the_current_shift_began() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"currentShiftStartedAt\":\"2026-09-26T07:00:00Z\"}");
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"currentShiftStartedAt\":null}");

        WorkerMetricsToday on = worker().metricsToday();
        WorkerMetricsToday off = worker().metricsToday();

        assertEquals("2026-09-26T07:00:00Z", on.getCurrentShiftStartedAt());
        assertNull(off.getCurrentShiftStartedAt());
    }

    // ─── workers.offboarding ───

    @Test
    void the_offboarding_preview_says_what_removal_would_hand_back() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"lastDay\":\"2026-09-30\","
                + "\"tasks\":{\"pending\":2,\"accepted\":1,\"booked\":3},"
                + "\"rosters\":[{\"rosterId\":\"r_1\",\"name\":\"October\",\"published\":true,\"shifts\":4}],"
                + "\"coverRequests\":1,\"coverOffers\":2,\"swaps\":3,\"pendingTimeOff\":4,"
                + "\"leavePolicies\":5,\"teams\":6}");

        WorkerOffboardingSummary summary = client().workers().offboarding("agent_1", "2026-09-30");

        RecordedRequest request = takeRequest();
        assertEquals("GET", request.getMethod());
        assertEquals("/v1/workers/agent_1/offboarding", pathOf(request));
        assertEquals("lastDay=2026-09-30", queryOf(request));
        assertEquals("agent_1", summary.getWorkerId());
        assertEquals("2026-09-30", summary.getLastDay());
        assertEquals(2, summary.getTasks().getPending());
        assertEquals(1, summary.getTasks().getAccepted());
        assertEquals(3, summary.getTasks().getBooked());
        WorkerOffboardingSummary.RosterShifts roster = summary.getRosters().get(0);
        assertEquals("r_1", roster.getRosterId());
        assertEquals("October", roster.getName());
        assertTrue(roster.isPublished());
        assertEquals(4, roster.getShifts());
        assertEquals(1, summary.getCoverRequests());
        assertEquals(2, summary.getCoverOffers());
        assertEquals(3, summary.getSwaps());
        assertEquals(4, summary.getPendingTimeOff());
        assertEquals(5, summary.getLeavePolicies());
        assertEquals(6, summary.getTeams());
    }

    @Test
    void the_offboarding_preview_defaults_to_today() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"lastDay\":\"2026-09-26\",\"rosters\":[]}");

        client().workers().offboarding("agent_1");

        assertEquals("", queryOf(takeRequest()));
    }

    // ─── workers.links / link / unlink ───

    @Test
    void links_are_listed_optionally_by_connector() throws Exception {
        enqueueJson(200, "{\"links\":[{\"connector\":\"hubspot\",\"vendorUserId\":\"owner_9\","
                + "\"workerId\":\"agent_1\",\"createdAt\":\"2026-09-01T00:00:00Z\"}]}");
        enqueueJson(200, "{}");

        List<WorkerLink> links = client().workers().links("hubspot");
        List<WorkerLink> none = client().workers().links();

        RecordedRequest first = takeRequest();
        assertEquals("/v1/workers/links", pathOf(first));
        assertEquals("connector=hubspot", queryOf(first));
        assertEquals("", queryOf(takeRequest()));
        WorkerLink link = links.get(0);
        assertEquals("hubspot", link.getConnector());
        assertEquals("owner_9", link.getVendorUserId());
        assertEquals("agent_1", link.getWorkerId());
        assertEquals("2026-09-01T00:00:00Z", link.getCreatedAt());
        assertTrue(none.isEmpty());
    }

    @Test
    void linking_is_a_put_on_the_worker_and_connector() throws Exception {
        enqueueJson(200, "{\"connector\":\"jira\",\"vendorUserId\":\"acc_1\",\"workerId\":\"agent_1\","
                + "\"createdAt\":\"x\"}");
        enqueueJson(200, "{\"connector\":\"jira\",\"vendorUserId\":\"acc_1\",\"workerId\":\"agent_1\"}");

        LinkWorkerInput input = new LinkWorkerInput("acc_1").tags(List.of("jira"));
        WorkerLink link = client().workers().link("agent_1", "jira", input);
        client().workers().link("agent_1", "jira", new LinkWorkerInput("acc_1"));

        RecordedRequest request = takeRequest();
        assertEquals("PUT", request.getMethod());
        assertEquals("/v1/workers/agent_1/links/jira", request.getPath());
        JsonObject body = bodyOf(request);
        assertEquals("acc_1", body.get("vendorUserId").getAsString());
        assertEquals("[\"jira\"]", body.get("tags").toString());
        assertEquals("{\"vendorUserId\":\"acc_1\"}", takeRequest().getBody().readUtf8());
        assertEquals("acc_1", link.getVendorUserId());
        assertEquals("acc_1", input.getVendorUserId());
        assertEquals(List.of("jira"), input.getTags());
    }

    @Test
    void a_link_needs_the_vendor_id() {
        assertThrows(IllegalArgumentException.class, () -> new LinkWorkerInput(null));
        assertThrows(IllegalArgumentException.class, () -> new LinkWorkerInput(""));
    }

    @Test
    void a_conflicting_link_raises_the_409() throws Exception {
        enqueueJson(409, "{\"error\":{\"code\":\"worker_link_conflict\",\"message\":\"taken\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().workers().link("agent_1", "jira", new LinkWorkerInput("acc_1")));

        assertEquals("worker_link_conflict", error.getCode());
    }

    @Test
    void unlinking_is_a_delete() throws Exception {
        enqueueEmpty(204);

        client().workers().unlink("agent_1", "hubspot");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("/v1/workers/agent_1/links/hubspot", request.getPath());
    }

    // ─── skills.expiring ───

    @Test
    void expiring_skills_send_the_window_and_read_lapsed_rows() throws Exception {
        enqueueJson(200, "{\"asOf\":\"2026-09-26\",\"skills\":[{\"workerId\":\"agent_1\",\"label\":\"Mari\","
                + "\"skillId\":\"sk_1\",\"key\":\"forklift\",\"name\":\"Forklift\","
                + "\"validUntil\":\"2026-09-20\",\"expired\":true}]}");

        ExpiringSkillsResult result = client().skills().expiring(14, "2026-09-26");

        RecordedRequest request = takeRequest();
        assertEquals("/v1/skills/expiring", pathOf(request));
        assertEquals("withinDays=14&asOf=2026-09-26", queryOf(request));
        assertEquals("2026-09-26", result.getAsOf());
        ExpiringWorkerSkill skill = result.getSkills().get(0);
        assertEquals("agent_1", skill.getWorkerId());
        assertEquals("Mari", skill.getLabel());
        assertEquals("sk_1", skill.getSkillId());
        assertEquals("forklift", skill.getKey());
        assertEquals("Forklift", skill.getName());
        assertEquals("2026-09-20", skill.getValidUntil());
        assertTrue(skill.isExpired());
    }

    @Test
    void expiring_skills_default_to_the_server_window() throws Exception {
        enqueueJson(200, "{\"asOf\":\"2026-09-26\",\"skills\":[]}");

        client().skills().expiring();

        assertEquals("", queryOf(takeRequest()));
    }

    // ─── team.time ───

    @Test
    void team_time_sends_its_filters_and_asks_for_entries_only_when_wanted() throws Exception {
        enqueueJson(200, "{\"from\":\"2026-09-01\",\"to\":\"2026-09-08\",\"workers\":[{\"workerId\":\"agent_1\","
                + "\"label\":\"Mari\",\"shiftCount\":5,\"onShiftMs\":100,\"breakCount\":2,\"breakMs\":10,"
                + "\"workingMs\":90,\"openShift\":true,\"openBreak\":false,\"completed\":7,\"offered\":9,"
                + "\"accepted\":8,\"rejected\":1,\"expired\":2,\"failed\":3,\"released\":4,"
                + "\"entries\":[{\"id\":\"s_1\",\"type\":\"shift\"}]}],"
                + "\"days\":[{\"day\":\"2026-09-01\",\"onShiftMs\":100,\"breakMs\":10,\"workingMs\":90}],"
                + "\"totals\":{\"workerCount\":1,\"shiftCount\":5,\"onShiftMs\":100,\"breakCount\":2,"
                + "\"breakMs\":10,\"workingMs\":90,\"completed\":7,\"offered\":9,\"accepted\":8,"
                + "\"rejected\":1,\"expired\":2,\"failed\":3},\"truncated\":true}");

        TeamTimeQuery query = new TeamTimeQuery().from("2026-09-01").to("2026-09-08")
                .teamId("team_1").workerId("agent_1").entries(true);
        TeamTimeResult result = client().team().time(query);

        RecordedRequest request = takeRequest();
        assertEquals("/v1/team/time", pathOf(request));
        assertEquals("from=2026-09-01&to=2026-09-08&teamId=team_1&workerId=agent_1&entries=true",
                queryOf(request));
        assertEquals("2026-09-01", query.getFrom());
        assertEquals("2026-09-08", query.getTo());
        assertEquals("team_1", query.getTeamId());
        assertEquals("agent_1", query.getWorkerId());
        assertTrue(query.isEntries());

        assertEquals("2026-09-01", result.getFrom());
        assertEquals("2026-09-08", result.getTo());
        assertTrue(result.isTruncated());
        TeamTimeWorker w = result.getWorkers().get(0);
        assertEquals("agent_1", w.getWorkerId());
        assertEquals("Mari", w.getLabel());
        assertEquals(5, w.getShiftCount());
        assertEquals(100L, w.getOnShiftMs());
        assertEquals(2, w.getBreakCount());
        assertEquals(10L, w.getBreakMs());
        assertEquals(90L, w.getWorkingMs());
        assertTrue(w.isOpenShift());
        assertFalse(w.isOpenBreak());
        assertEquals(7, w.getCompleted());
        assertEquals(9, w.getOffered());
        assertEquals(8, w.getAccepted());
        assertEquals(1, w.getRejected());
        assertEquals(2, w.getExpired());
        assertEquals(3, w.getFailed());
        assertEquals(4, w.getReleased());
        assertEquals("s_1", w.getEntries().get(0).getId());
        TeamTimeDay day = result.getDays().get(0);
        assertEquals("2026-09-01", day.getDay());
        assertEquals(100L, day.getOnShiftMs());
        assertEquals(10L, day.getBreakMs());
        assertEquals(90L, day.getWorkingMs());
        TeamTimeResult.Totals t = result.getTotals();
        assertEquals(1, t.getWorkerCount());
        assertEquals(5, t.getShiftCount());
        assertEquals(100L, t.getOnShiftMs());
        assertEquals(2, t.getBreakCount());
        assertEquals(10L, t.getBreakMs());
        assertEquals(90L, t.getWorkingMs());
        assertEquals(7, t.getCompleted());
        assertEquals(9, t.getOffered());
        assertEquals(8, t.getAccepted());
        assertEquals(1, t.getRejected());
        assertEquals(2, t.getExpired());
        assertEquals(3, t.getFailed());
    }

    @Test
    void team_time_for_today_sends_no_query_and_reads_no_day_series() throws Exception {
        enqueueJson(200, "{\"from\":\"a\",\"to\":\"b\",\"workers\":[{\"workerId\":\"agent_1\"}],\"days\":null,"
                + "\"totals\":{},\"truncated\":false}");

        TeamTimeResult result = client().team().time();

        assertEquals("", queryOf(takeRequest()));
        assertNull(result.getDays());
        assertNull(result.getWorkers().get(0).getEntries());
        assertFalse(result.isTruncated());
    }
}
