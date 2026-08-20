package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertFalse;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertTrue;

import io.fivexer.sdk.model.CreateNotificationChannel;
import io.fivexer.sdk.model.CreateNotificationSequence;
import io.fivexer.sdk.model.LearnedWeightsPreview;
import io.fivexer.sdk.model.LearningFeedbackItem;
import io.fivexer.sdk.model.LearningFeedbackResult;
import io.fivexer.sdk.model.LearningStatus;
import io.fivexer.sdk.model.NotificationChannel;
import io.fivexer.sdk.model.NotificationSequence;
import io.fivexer.sdk.model.NotificationSequenceStep;
import io.fivexer.sdk.model.UpdateNotificationChannel;
import io.fivexer.sdk.model.UpdateNotificationSequence;
import io.fivexer.sdk.model.WorkerLearningStats;
import java.util.List;
import java.util.Map;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/** The learning layer, and the notification sequences and channels that fan out its events. */
class LearningAndNotificationsTest extends MockServerBase {

    private static final String SEQUENCE_JSON = "{\"id\":\"seq_1\",\"name\":\"Escalate stale tasks\","
            + "\"enabled\":true,\"steps\":[{\"trigger\":\"matched\",\"offsetMs\":300000,"
            + "\"eventType\":\"task.expiring\"}],\"filterTags\":[\"billing\"],"
            + "\"createdAt\":\"2026-07-24T10:00:00.000Z\",\"updatedAt\":\"2026-07-24T10:00:00.000Z\"}";

    private static final String CHANNEL_JSON = "{\"id\":\"ch_1\",\"type\":\"webhook\","
            + "\"target\":\"https://hooks.example/fivexer\",\"events\":[\"task.matched\"],"
            + "\"disabled\":false,\"createdAt\":\"2026-07-24T10:00:00.000Z\"}";

    // ---- learning ----

    @Test
    void reading_learning_status_reports_whether_it_is_shadowing() throws Exception {
        // Shadow mode means the model scores but never influences routing — an important
        // distinction when interpreting the numbers.
        enqueueJson(200, "{\"enabled\":true,\"shadowMode\":true,\"autoWeights\":false,"
                + "\"signalWeights\":{\"csat\":1.0},\"rewards\":{\"accept\":1,\"complete\":2},"
                + "\"stats\":{\"decisions\":1200,\"rewards\":800,\"totalReward\":940.5,"
                + "\"averageReward\":1.175},\"modelSize\":64}");

        LearningStatus status = client().learning().status();

        assertTrue(status.isEnabled());
        assertTrue(status.isShadowMode());
        assertFalse(status.isAutoWeights());
        assertEquals(64, status.getModelSize());
        assertEquals(1.0, status.getSignalWeights().get("csat"));
        assertEquals(2.0, status.getRewards().get("complete"));
        assertEquals(1200, status.getStats().getDecisions());
        assertEquals(800, status.getStats().getRewards());
        assertEquals(940.5, status.getStats().getTotalReward());
        assertEquals(1.175, status.getStats().getAverageReward());
    }

    @Test
    void learning_status_before_any_reward_has_no_statistics() throws Exception {
        enqueueJson(200, "{\"enabled\":false,\"shadowMode\":true,\"autoWeights\":false,"
                + "\"stats\":null,\"modelSize\":0}");

        assertNull(client().learning().status().getStats());
    }

    @Test
    void per_worker_stats_separate_learned_weights_from_configured_ones() throws Exception {
        enqueueJson(200, "{\"workerId\":\"agent_1\",\"skills\":["
                + "{\"tag\":\"english\",\"count\":40,\"meanReward\":1.4,\"currentWeight\":100,"
                + "\"learnedWeight\":118,\"skill\":null},"
                + "{\"tag\":\"skill:skl_1\",\"count\":12,\"meanReward\":0.2,\"currentWeight\":null,"
                + "\"learnedWeight\":55,\"skill\":{\"id\":\"skl_1\",\"name\":\"Refunds\"}}]}");

        WorkerLearningStats stats = client().learning().workerStats("agent_1");

        assertEquals("/v1/learning/workers/agent_1", takeRequest().getPath());
        assertEquals("agent_1", stats.getWorkerId());
        assertEquals("english", stats.getSkills().get(0).getTag());
        assertEquals(40, stats.getSkills().get(0).getCount());
        assertEquals(1.4, stats.getSkills().get(0).getMeanReward());
        assertEquals(100.0, stats.getSkills().get(0).getCurrentWeight());
        assertEquals(118.0, stats.getSkills().get(0).getLearnedWeight());
        assertNull(stats.getSkills().get(0).getSkill());
        // A tag with no configured weight is still learnable.
        assertNull(stats.getSkills().get(1).getCurrentWeight());
        assertEquals("Refunds", stats.getSkills().get(1).getSkill().get("name"));
    }

    @Test
    void previewing_weights_for_one_worker_scopes_the_request() throws Exception {
        enqueueJson(200, "{\"workers\":[{\"workerId\":\"agent_1\",\"current\":{\"english\":100},"
                + "\"learned\":{\"english\":118}}]}");

        LearnedWeightsPreview preview = client().learning().previewWeights("agent_1");

        assertEquals("workerId=agent_1", queryOf(takeRequest()));
        assertEquals("agent_1", preview.getWorkers().get(0).getWorkerId());
        assertEquals(100.0, preview.getWorkers().get(0).getCurrent().get("english"));
        assertEquals(118.0, preview.getWorkers().get(0).getLearned().get("english"));
    }

    @Test
    void previewing_weights_for_everyone_sends_no_scope() throws Exception {
        enqueueJson(200, "{\"workers\":[]}");

        client().learning().previewWeights();

        assertEquals("/v1/learning/weights/preview", takeRequest().getPath());
    }

    @Test
    void applying_learned_weights_reports_what_changed_per_worker() throws Exception {
        enqueueJson(200, "{\"applied\":{\"agent_1\":{\"english\":118}}}");

        Map<String, Map<String, Double>> applied = client().learning().applyWeights(List.of("agent_1"));

        RecordedRequest request = takeRequest();
        assertEquals("agent_1", bodyOf(request).getAsJsonArray("workerIds").get(0).getAsString());
        assertEquals(118.0, applied.get("agent_1").get("english"));
    }

    @Test
    void applying_weights_to_every_worker_sends_an_empty_body() throws Exception {
        enqueueJson(200, "{\"applied\":{}}");

        assertTrue(client().learning().applyWeights().isEmpty());
        assertEquals(0, bodyOf(takeRequest()).size());
    }

    @Test
    void applying_weights_when_nothing_was_learned_returns_an_empty_map() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().learning().applyWeights().isEmpty());
    }

    @Test
    void reporting_outcome_signals_against_a_task() throws Exception {
        enqueueJson(200, "{\"ok\":true}");

        assertTrue(client().learning().feedback("task_8fk2", Map.of("csat", 0.9)));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/task_8fk2/feedback", request.getPath());
        assertEquals(0.9, bodyOf(request).getAsJsonObject("signals").get("csat").getAsDouble());
    }

    @Test
    void reporting_a_direct_reward_against_a_task() throws Exception {
        enqueueJson(200, "{\"ok\":true}");

        assertTrue(client().learning().reward("task_8fk2", 1.5));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/tasks/task_8fk2/reward", request.getPath());
        assertEquals(1.5, bodyOf(request).get("reward").getAsDouble());
    }

    @Test
    void bulk_feedback_reports_failures_per_item_rather_than_failing_the_batch() throws Exception {
        enqueueJson(200, "{\"results\":[{\"taskId\":\"task_8fk2\",\"ok\":true},"
                + "{\"taskId\":\"missing\",\"ok\":false,\"error\":\"no decision recorded for task\"}]}");

        List<LearningFeedbackResult> results = client().learning().feedbackBulk(List.of(
                new LearningFeedbackItem("task_8fk2").reward(1.0),
                new LearningFeedbackItem("missing").signals(Map.of("csat", 1.0))));

        assertEquals(2, bodyOf(takeRequest()).getAsJsonArray("items").size());
        assertTrue(results.get(0).isOk());
        assertNull(results.get(0).getError());
        assertFalse(results.get(1).isOk());
        assertEquals("missing", results.get(1).getTaskId());
        assertEquals("no decision recorded for task", results.get(1).getError());
    }

    @Test
    void bulk_feedback_with_no_results_returns_an_empty_list() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().learning().feedbackBulk(List.of()).isEmpty());
    }

    @Test
    void resetting_discards_the_learned_model() throws Exception {
        enqueueJson(200, "{\"ok\":true}");

        assertTrue(client().learning().reset());
        assertEquals("/v1/learning/reset", takeRequest().getPath());
    }

    @Test
    void a_refused_reset_is_reported_as_not_ok() throws Exception {
        enqueueJson(200, "{\"ok\":false}");

        assertFalse(client().learning().reset());
    }

    // ---- notification sequences ----

    @Test
    void listing_sequences_unwraps_the_envelope() throws Exception {
        enqueueJson(200, "{\"sequences\":[" + SEQUENCE_JSON + "]}");

        List<NotificationSequence> sequences = client().notifications().sequences().list();

        assertEquals("seq_1", sequences.get(0).getId());
        assertEquals("Escalate stale tasks", sequences.get(0).getName());
        assertTrue(sequences.get(0).isEnabled());
        assertEquals(List.of("billing"), sequences.get(0).getFilterTags());
        assertEquals("2026-07-24T10:00:00.000Z", sequences.get(0).getCreatedAt());
        assertEquals("2026-07-24T10:00:00.000Z", sequences.get(0).getUpdatedAt());
        assertEquals("matched", sequences.get(0).getSteps().get(0).getTrigger());
        assertEquals("task.expiring", sequences.get(0).getSteps().get(0).getEventType());
        assertEquals(300000L, sequences.get(0).getSteps().get(0).getOffsetMs());
    }

    @Test
    void listing_sequences_in_an_empty_workspace_returns_an_empty_list() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().notifications().sequences().list().isEmpty());
    }

    @Test
    void creating_a_sequence_schedules_its_steps_relative_to_a_trigger() throws Exception {
        enqueueJson(201, SEQUENCE_JSON);

        client().notifications().sequences().create(
                new CreateNotificationSequence("Escalate stale tasks",
                        List.of(new NotificationSequenceStep("matched", "task.expiring").offsetMs(300000)))
                        .filterTags(List.of("billing")));

        RecordedRequest request = takeRequest();
        assertEquals("/v1/notification-sequences", request.getPath());
        assertEquals(300000, bodyOf(request).getAsJsonArray("steps").get(0).getAsJsonObject()
                .get("offsetMs").getAsInt());
    }

    @Test
    void reading_a_sequence_by_id() throws Exception {
        enqueueJson(200, SEQUENCE_JSON);

        assertEquals("Escalate stale tasks", client().notifications().sequences().get("seq_1").getName());
        assertEquals("/v1/notification-sequences/seq_1", takeRequest().getPath());
    }

    @Test
    void disabling_a_sequence_leaves_its_steps_intact() throws Exception {
        // A PATCH with only `enabled` must not blank the steps it does not mention.
        enqueueJson(200, SEQUENCE_JSON.replace("\"enabled\":true", "\"enabled\":false"));

        NotificationSequence sequence = client().notifications().sequences()
                .update("seq_1", new UpdateNotificationSequence().enabled(false));

        assertEquals(1, bodyOf(takeRequest()).size());
        assertFalse(sequence.isEnabled());
        assertEquals(1, sequence.getSteps().size());
    }

    @Test
    void removing_a_sequence_returns_nothing() throws Exception {
        enqueueEmpty(204);

        client().notifications().sequences().remove("seq_1");

        assertEquals("DELETE", takeRequest().getMethod());
    }

    // ---- notification channels ----

    @Test
    void listing_channels_unwraps_the_envelope() throws Exception {
        enqueueJson(200, "{\"channels\":[" + CHANNEL_JSON + "]}");

        List<NotificationChannel> channels = client().notifications().channels().list();

        assertEquals("ch_1", channels.get(0).getId());
        assertEquals("webhook", channels.get(0).getType());
        assertEquals("https://hooks.example/fivexer", channels.get(0).getTarget());
        assertEquals(List.of("task.matched"), channels.get(0).getEvents());
        assertFalse(channels.get(0).isDisabled());
        assertEquals("2026-07-24T10:00:00.000Z", channels.get(0).getCreatedAt());
    }

    @Test
    void listing_channels_in_an_empty_workspace_returns_an_empty_list() throws Exception {
        enqueueJson(200, "{}");

        assertTrue(client().notifications().channels().list().isEmpty());
    }

    @Test
    void creating_a_webhook_channel_sends_the_signing_secret_but_never_reads_it_back()
            throws Exception {
        enqueueJson(201, CHANNEL_JSON);

        NotificationChannel channel = client().notifications().channels().create(
                new CreateNotificationChannel("webhook", "https://hooks.example/fivexer",
                        List.of("task.matched")).secret("whsec_abc"));

        assertEquals("whsec_abc", bodyOf(takeRequest()).get("secret").getAsString());
        assertEquals("ch_1", channel.getId());
    }

    @Test
    void reading_a_channel_by_id() throws Exception {
        enqueueJson(200, CHANNEL_JSON);

        assertEquals("https://hooks.example/fivexer",
                client().notifications().channels().get("ch_1").getTarget());
        assertEquals("/v1/notification-channels/ch_1", takeRequest().getPath());
    }

    @Test
    void disabling_a_channel_stops_delivery_without_deleting_it() throws Exception {
        enqueueJson(200, CHANNEL_JSON.replace("\"disabled\":false", "\"disabled\":true"));

        NotificationChannel channel = client().notifications().channels()
                .update("ch_1", new UpdateNotificationChannel().disabled(true));

        assertEquals(1, bodyOf(takeRequest()).size());
        assertTrue(channel.isDisabled());
        assertEquals("ch_1", channel.getId());
    }

    @Test
    void removing_a_channel_returns_nothing() throws Exception {
        enqueueEmpty(204);

        client().notifications().channels().remove("ch_1");

        assertEquals("DELETE", takeRequest().getMethod());
    }
}
