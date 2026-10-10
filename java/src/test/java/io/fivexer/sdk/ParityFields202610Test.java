package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import io.fivexer.sdk.model.CorrectTimeEntryInput;
import io.fivexer.sdk.model.CreateTimeEntryInput;
import io.fivexer.sdk.model.PublicWorkerIdentity;
import io.fivexer.sdk.model.RoleStretchInput;
import io.fivexer.sdk.model.WorkerBreakStarted;
import io.fivexer.sdk.model.WorkerMe;
import io.fivexer.sdk.model.WorkerOffboardingSummary;
import io.fivexer.sdk.model.WorkerTimeEntry;
import java.util.List;
import org.junit.jupiter.api.Test;

/**
 * Fields the TypeScript SDK already reads and these models had not caught up with: time-entry
 * roles, worker photos, break coverage warnings, and the leave settlement on offboarding.
 */
class ParityFields202610Test {
    private static final Gson GSON = new Gson();
    private static final String WARNING =
            "{\"shiftInstanceId\":\"si_1\",\"shiftName\":\"Early\",\"remaining\":1,\"required\":2,\"kind\":\"headcount\"}";

    @Test
    void aTimeEntryReadsItsRolesAndKeepsNullForAShiftWithout() {
        WorkerTimeEntry entry = GSON.fromJson(
                "{\"type\":\"shift\",\"roles\":["
                        + "{\"positionId\":\"pos_1\",\"startedAt\":\"a\",\"endedAt\":\"b\",\"label\":\"Forklift\"},"
                        + "{\"positionId\":null,\"startedAt\":\"b\",\"endedAt\":null,\"label\":null}]}",
                WorkerTimeEntry.class);
        assertEquals("Forklift", entry.getRoles().get(0).getLabel());
        assertNull(entry.getRoles().get(1).getPositionId());
        assertNull(entry.getRoles().get(1).getEndedAt());
        assertNull(GSON.fromJson("{\"type\":\"shift\"}", WorkerTimeEntry.class).getRoles());
    }

    @Test
    void timeEntryWritesSendRolesWithTheirNulls() {
        List<RoleStretchInput> roles = List.of(new RoleStretchInput(null, "a", null));
        JsonObject created = JsonParser.parseString(new CreateTimeEntryInput("a", "b", "forgot").roles(roles).toJson())
                .getAsJsonObject();
        JsonObject stretch = created.getAsJsonArray("roles").get(0).getAsJsonObject();
        assertTrue(stretch.has("positionId") && stretch.get("positionId").isJsonNull());
        assertTrue(stretch.has("endedAt") && stretch.get("endedAt").isJsonNull());
        assertFalse(JsonParser.parseString(new CreateTimeEntryInput("a", "b", "n").toJson()).getAsJsonObject().has("roles"));
        JsonObject corrected = JsonParser.parseString(new CorrectTimeEntryInput("wrong role").roles(roles).toJson())
                .getAsJsonObject();
        assertEquals("a", corrected.getAsJsonArray("roles").get(0).getAsJsonObject().get("startedAt").getAsString());
    }

    @Test
    void photosAndBreakWarningsOnTheWorker() {
        WorkerMe me = GSON.fromJson(
                "{\"workerId\":\"w1\",\"photoUrl\":\"/v1/worker-photos/abc\",\"photoUploads\":true,\"breakCoverageWarnings\":["
                        + WARNING + "]}",
                WorkerMe.class);
        assertEquals("/v1/worker-photos/abc", me.getPhotoUrl());
        assertTrue(me.getPhotoUploads());
        assertEquals("Early", me.getBreakCoverageWarnings().get(0).getShiftName());
        WorkerMe bare = GSON.fromJson("{\"workerId\":\"w1\"}", WorkerMe.class);
        assertNull(bare.getPhotoUrl());
        assertFalse(bare.getPhotoUploads());
        assertTrue(bare.getBreakCoverageWarnings().isEmpty());
        assertEquals(
                "/v1/worker-photos/abc",
                GSON.fromJson("{\"id\":\"wi_1\",\"photoUrl\":\"/v1/worker-photos/abc\"}", PublicWorkerIdentity.class).getPhotoUrl());
    }

    @Test
    void aBreakReportsTheShiftsItLeavesShort() {
        WorkerBreakStarted started = GSON.fromJson(
                "{\"workerId\":\"w1\",\"since\":\"x\",\"onBreak\":true,\"coverageWarnings\":[" + WARNING + "]}",
                WorkerBreakStarted.class);
        assertEquals(1, started.getCoverageWarnings().get(0).getRemaining());
        assertEquals("headcount", started.getCoverageWarnings().get(0).getKind());
    }

    @Test
    void offboardingReadsTheLeaveSettlement() {
        WorkerOffboardingSummary summary = GSON.fromJson(
                "{\"workerId\":\"w1\",\"lastDay\":\"2026-10-31\",\"leavePayout\":{\"paidOutMinutes\":960,\"recoveredMinutes\":0},"
                        + "\"futureLeave\":2,\"leaveSettlementError\":null}",
                WorkerOffboardingSummary.class);
        assertEquals(960, summary.getLeavePayout().getPaidOutMinutes());
        assertEquals(2, summary.getFutureLeave());
        assertNull(summary.getLeaveSettlementError());
        WorkerOffboardingSummary older =
                GSON.fromJson("{\"workerId\":\"w1\",\"lastDay\":\"2026-10-31\"}", WorkerOffboardingSummary.class);
        assertNull(older.getLeavePayout());
        assertEquals(0, older.getFutureLeave());
    }
}
