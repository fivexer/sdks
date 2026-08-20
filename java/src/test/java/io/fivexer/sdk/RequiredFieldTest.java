package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertThrows;

import io.fivexer.sdk.model.AcceptWorkerInvite;
import io.fivexer.sdk.model.AddComment;
import io.fivexer.sdk.model.ChangePin;
import io.fivexer.sdk.model.CreateJoinLink;
import io.fivexer.sdk.model.CreateAttachment;
import io.fivexer.sdk.model.CreateNotificationChannel;
import io.fivexer.sdk.model.CreateNotificationSequence;
import io.fivexer.sdk.model.CreateWorkerIdentity;
import io.fivexer.sdk.model.InviteWorkerIdentity;
import io.fivexer.sdk.model.JoinWorkspace;
import io.fivexer.sdk.model.PushSubscriptionInput;
import io.fivexer.sdk.model.RequiredSkill;
import io.fivexer.sdk.model.UpsertTeam;
import io.fivexer.sdk.model.UpsertSkill;
import io.fivexer.sdk.model.WorkerLogin;
import io.fivexer.sdk.model.WorkerDeviceInput;
import io.fivexer.sdk.model.WorkerLocation;
import io.fivexer.sdk.model.WorkerSkillAssignment;
import io.fivexer.sdk.model.WorkerSkillLevel;
import java.util.List;
import org.junit.jupiter.api.Test;

/**
 * Required fields are refused at construction, not at the server.
 *
 * <p>Catching a missing filename or PIN locally turns a 400 round trip into an immediate,
 * precisely-named error — and each guard is checked here because a copy-paste slip between two
 * dozen near-identical constructors would otherwise let a required field through unvalidated.
 */
class RequiredFieldTest {

    private static void rejects(String field, Executable construct) {
        IllegalArgumentException error = assertThrows(IllegalArgumentException.class, construct::run);
        assertEquals(field + " is required", error.getMessage());
    }

    /** A construction that is expected to throw. */
    private interface Executable {
        void run();
    }

    @Test
    void an_attachment_needs_a_filename_a_content_type_and_a_size() {
        rejects("filename", () -> new CreateAttachment(null, "application/pdf", 1L));
        rejects("contentType", () -> new CreateAttachment("a.pdf", null, 1L));
        rejects("sizeBytes", () -> new CreateAttachment("a.pdf", "application/pdf", null));
    }

    @Test
    void a_comment_needs_a_body() {
        rejects("body", () -> new AddComment(null));
    }

    @Test
    void a_skill_needs_a_key_and_a_name() {
        rejects("key", () -> new UpsertSkill(null, "Refunds"));
        rejects("name", () -> new UpsertSkill("refunds", null));
    }

    @Test
    void a_skill_assignment_needs_a_skill_and_a_level() {
        rejects("skillId", () -> new WorkerSkillAssignment(null, 3));
        rejects("level", () -> new WorkerSkillAssignment("skl_1", null));
    }

    @Test
    void a_required_skill_filter_needs_a_skill_and_a_minimum_level() {
        rejects("skillId", () -> new RequiredSkill(null, 3));
        rejects("minLevel", () -> new RequiredSkill("skl_1", null));
    }

    @Test
    void a_sequence_needs_a_name_and_steps() {
        rejects("name", () -> new CreateNotificationSequence(null, List.of()));
        rejects("steps", () -> new CreateNotificationSequence("Escalate", null));
    }

    @Test
    void a_channel_needs_a_type_a_target_and_events() {
        rejects("type", () -> new CreateNotificationChannel(null, "https://h", List.of()));
        rejects("target", () -> new CreateNotificationChannel("webhook", null, List.of()));
        rejects("events", () -> new CreateNotificationChannel("webhook", "https://h", null));
    }

    @Test
    void a_worker_login_needs_all_three_credentials() {
        rejects("workspaceId", () -> new WorkerLogin(null, "agent_1", "4821"));
        rejects("workerId", () -> new WorkerLogin("ws_1", null, "4821"));
        rejects("pin", () -> new WorkerLogin("ws_1", "agent_1", null));
    }

    @Test
    void a_team_needs_a_key_and_a_name() {
        rejects("key", () -> new UpsertTeam(null, "Billing"));
        rejects("name", () -> new UpsertTeam("billing", null));
    }

    @Test
    void a_join_link_needs_a_label() {
        rejects("label", () -> new CreateJoinLink(null));
    }

    @Test
    void an_invite_needs_an_email_and_a_direct_identity_needs_a_label_and_pin() {
        rejects("email", () -> new InviteWorkerIdentity(null));
        rejects("label", () -> new CreateWorkerIdentity(null, "4821"));
        rejects("pin", () -> new CreateWorkerIdentity("Ada", null));
    }

    @Test
    void joining_needs_a_token_a_name_and_a_pin() {
        rejects("token", () -> new JoinWorkspace(null, "Ada", "4821"));
        rejects("name", () -> new JoinWorkspace("jt", null, "4821"));
        rejects("pin", () -> new JoinWorkspace("jt", "Ada", null));
    }

    @Test
    void accepting_an_invite_needs_a_token_and_a_pin() {
        rejects("token", () -> new AcceptWorkerInvite(null, "4821"));
        rejects("pin", () -> new AcceptWorkerInvite("it", null));
    }

    @Test
    void changing_a_pin_needs_both_the_old_and_the_new_one() {
        rejects("currentPin", () -> new ChangePin(null, "4821"));
        rejects("newPin", () -> new ChangePin("1111", null));
    }

    @Test
    void a_location_needs_both_coordinates() {
        rejects("latitude", () -> new WorkerLocation(null, 24.7));
        rejects("longitude", () -> new WorkerLocation(59.4, null));
    }

    @Test
    void a_claimed_skill_needs_an_id_and_a_level() {
        rejects("skillId", () -> new WorkerSkillLevel(null, 3));
        rejects("level", () -> new WorkerSkillLevel("sk_1", null));
    }

    @Test
    void a_device_needs_a_token() {
        rejects("token", () -> new WorkerDeviceInput(null));
    }

    @Test
    void a_push_subscription_needs_an_endpoint_and_both_keys() {
        // All three come straight from the browser's PushSubscription; a missing one means the
        // caller passed something that was not `subscription.toJSON()`.
        rejects("endpoint", () -> new PushSubscriptionInput(null, "p2", "au"));
        rejects("p256dh", () -> new PushSubscriptionInput("https://fcm/x", null, "au"));
        rejects("auth", () -> new PushSubscriptionInput("https://fcm/x", "p2", null));
    }
}
