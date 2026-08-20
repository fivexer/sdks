package io.fivexer.sdk;

import io.fivexer.sdk.webhook.Webhook;
import io.fivexer.sdk.webhook.WebhookEvent;
import org.junit.jupiter.api.Test;

import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;

import static org.junit.jupiter.api.Assertions.*;

/**
 * Webhook signature verification: replay window, malformed headers, wrong secret,
 * tampered body, custom tolerance, and the platform header constant.
 */
class WebhookTest {

    private static final String SECRET = "whsec_testsecret";
    private static final long NOW = 1750000000000L;

    private static byte[] body() {
        return "{\"event\":\"task.matched\",\"taskId\":\"task_8fk2\",\"workerId\":\"agent_1\"}"
                .getBytes(StandardCharsets.UTF_8);
    }

    private static String sign(String secret, long timestamp, byte[] body) throws Exception {
        Mac mac = Mac.getInstance("HmacSHA256");
        mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
        mac.update(Long.toString(timestamp).getBytes(StandardCharsets.US_ASCII));
        mac.update((byte) '.');
        mac.update(body);
        return "t=" + timestamp + ",v1=" + bytesToHex(mac.doFinal());
    }

    private static String bytesToHex(byte[] bytes) {
        StringBuilder sb = new StringBuilder();
        for (byte b : bytes) {
            sb.append(String.format("%02x", b));
        }
        return sb.toString();
    }

    @Test
    void a_validly_signed_webhook_is_verified_and_parsed() throws Exception {
        byte[] raw = body();
        String header = sign(SECRET, NOW, raw);

        WebhookEvent event = Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW);

        assertEquals("task.matched", event.getEvent());
        assertEquals("task_8fk2", event.getData().get("taskId"));
        assertEquals("agent_1", event.getData().get("workerId"));
    }

    @Test
    void a_webhook_signed_with_the_wrong_secret_is_rejected() throws Exception {
        byte[] raw = body();
        String header = sign("whsec_wrong", NOW, raw);

        assertThrows(FivexerException.class,
                () -> Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void a_webhook_outside_the_replay_window_is_rejected() throws Exception {
        byte[] raw = body();
        String header = sign(SECRET, NOW - (10L * 60 * 1000), raw); // 10 min ago

        assertThrows(FivexerException.class,
                () -> Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void a_tampered_body_fails_verification() throws Exception {
        byte[] raw = body();
        String header = sign(SECRET, NOW, raw);
        byte[] tampered = new String(raw, StandardCharsets.UTF_8).replace("task_8fk2", "task_9999")
                .getBytes(StandardCharsets.UTF_8);

        assertThrows(FivexerException.class,
                () -> Webhook.constructEvent(tampered, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void a_custom_tolerance_window_can_be_widened() throws Exception {
        byte[] raw = body();
        String header = sign(SECRET, NOW - (6L * 60 * 1000), raw); // 6 min old

        // default tolerance (5 min) rejects
        assertThrows(FivexerException.class,
                () -> Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW));
        // widened tolerance (10 min) accepts
        WebhookEvent event = Webhook.constructEvent(raw, header, SECRET, 10L * 60 * 1000, NOW);
        assertEquals("task.matched", event.getEvent());
    }

    @Test
    void malformed_signature_headers_are_rejected() {
        byte[] raw = body();
        for (String bad : new String[]{"", "garbage", "t=abc,v1=def", "t=123", "v1=abc",
                "t=123,v1=" + "x".repeat(64), "x=1,v1=" + "0".repeat(64)}) {
            assertThrows(FivexerException.class,
                    () -> Webhook.constructEvent(raw, bad, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW),
                    "expected rejection for header: " + bad);
        }
    }

    @Test
    void a_payload_without_an_event_key_yields_null_event() throws Exception {
        byte[] raw = "{\"taskId\":\"task_1\",\"workerId\":\"a1\"}".getBytes(StandardCharsets.UTF_8);
        String header = sign(SECRET, NOW, raw);

        WebhookEvent event = Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW);
        assertNull(event.getEvent());
        assertEquals("task_1", event.getData().get("taskId"));
    }

    @Test
    void signature_header_constant_is_the_platform_value() {
        assertEquals("x-fivexer-signature", Webhook.SIGNATURE_HEADER);
    }

    @Test
    void verify_signature_returns_false_without_raising_for_garbage() {
        assertFalse(Webhook.verifySignature(new byte[]{}, "garbage", SECRET,
                Webhook.DEFAULT_TOLERANCE_MS, NOW));
    }

    @Test
    void non_json_payload_is_rejected_with_a_clear_error() {
        byte[] raw = "not json".getBytes(StandardCharsets.UTF_8);
        // sign it so the signature passes, then parsing must fail clearly
        assertThrows(FivexerException.class, () -> {
            String header = sign(SECRET, NOW, raw);
            Webhook.constructEvent(raw, header, SECRET, Webhook.DEFAULT_TOLERANCE_MS, NOW);
        });
    }
}
