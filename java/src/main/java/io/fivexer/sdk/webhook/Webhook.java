package io.fivexer.sdk.webhook;

import io.fivexer.sdk.FivexerException;
import java.nio.charset.StandardCharsets;
import java.security.MessageDigest;
import java.util.Objects;
import javax.crypto.Mac;
import javax.crypto.spec.SecretKeySpec;

/**
 * Webhook signature verification for the {@code x-fivexer-signature} header.
 *
 * <p>Mirrors the platform's signing exactly:
 * <pre>
 *   header  : x-fivexer-signature
 *   format  : t=&lt;unix-ms&gt;,v1=&lt;hex64 HMAC-SHA256&gt;
 *   message : "&lt;timestamp&gt;.&lt;raw-body&gt;"
 *   replay  : rejected if |now - timestamp| &gt; tolerance (default ±5 min)
 *   compare : constant-time (MessageDigest.isEqual)
 * </pre>
 *
 * <p>The body must be the <b>raw</b> request body bytes — exactly what was received, before
 * any JSON re-serialization, otherwise the HMAC will not match.
 */
public final class Webhook {

    public static final String SIGNATURE_HEADER = "x-fivexer-signature";
    public static final long DEFAULT_TOLERANCE_MS = 5L * 60 * 1000;

    private Webhook() {}

    /** Verify the signature and return the parsed event. Throws {@link FivexerException} on any failure. */
    public static WebhookEvent constructEvent(byte[] payload, String header, String secret) {
        return constructEvent(payload, header, secret, DEFAULT_TOLERANCE_MS, System.currentTimeMillis());
    }

    public static WebhookEvent constructEvent(byte[] payload, String header, String secret, long toleranceMs) {
        return constructEvent(payload, header, secret, toleranceMs, System.currentTimeMillis());
    }

    public static WebhookEvent constructEvent(byte[] payload, String header, String secret, long toleranceMs, long nowMs) {
        if (!verifySignature(payload, header, secret, toleranceMs, nowMs)) {
            throw new FivexerException("webhook signature verification failed");
        }
        return WebhookEvent.fromPayload(payload);
    }

    /** @return {@code true} iff the header is well-formed, within tolerance, and the HMAC matches. */
    public static boolean verifySignature(byte[] payload, String header, String secret, long toleranceMs, long nowMs) {
        Objects.requireNonNull(payload, "payload");
        ParsedSig parsed = parseSignatureHeader(header);
        if (parsed == null) {
            return false;
        }
        if (Math.abs(nowMs - parsed.timestamp) > toleranceMs) {
            return false;
        }
        byte[] expected = computeSignature(secret, parsed.timestamp, payload);
        byte[] supplied = parsed.signatureBytes;
        // parseSignatureHeader guarantees exactly 64 hex chars → 32 bytes, matching expected;
        // MessageDigest.isEqual is constant-time and also guards length.
        return MessageDigest.isEqual(expected, supplied);
    }

    // ---- internals ----

    private static byte[] computeSignature(String secret, long timestamp, byte[] body) {
        try {
            Mac mac = Mac.getInstance("HmacSHA256");
            mac.init(new SecretKeySpec(secret.getBytes(StandardCharsets.UTF_8), "HmacSHA256"));
            mac.update(Long.toString(timestamp).getBytes(StandardCharsets.US_ASCII));
            mac.update((byte) '.');
            mac.update(body);
            return mac.doFinal();
        } catch (Exception e) {
            throw new FivexerException("failed to compute webhook signature", e);
        }
    }

    /** Parsed {@code t=<digits>,v1=<64 hex>} into timestamp + raw signature bytes. */
    private static final class ParsedSig {
        final long timestamp;
        final byte[] signatureBytes;

        ParsedSig(long timestamp, byte[] signatureBytes) {
            this.timestamp = timestamp;
            this.signatureBytes = signatureBytes;
        }
    }

    private static ParsedSig parseSignatureHeader(String header) {
        if (header == null) {
            return null;
        }
        String trimmed = header.trim();
        // limit -1 preserves trailing empty strings so Python parity holds for malformed headers
        String[] parts = trimmed.split(",", -1);
        if (parts.length != 2) {
            return null;
        }
        String tPart = parts[0].trim();
        String vPart = parts[1].trim();
        if (!tPart.startsWith("t=") || !vPart.startsWith("v1=")) {
            return null;
        }
        String tsStr = tPart.substring(2);
        String sig = vPart.substring(3);
        for (int i = 0; i < tsStr.length(); i++) {
            if (!Character.isDigit(tsStr.charAt(i))) {
                return null;
            }
        }
        if (tsStr.isEmpty() || sig.length() != 64 || !isHex(sig)) {
            return null;
        }
        byte[] sigBytes = hexToBytes(sig.toLowerCase());
        return new ParsedSig(Long.parseLong(tsStr), sigBytes);
    }

    private static boolean isHex(String s) {
        for (int i = 0; i < s.length(); i++) {
            char c = s.charAt(i);
            if (!((c >= '0' && c <= '9') || (c >= 'a' && c <= 'f') || (c >= 'A' && c <= 'F'))) {
                return false;
            }
        }
        return true;
    }

    private static byte[] hexToBytes(String hex) {
        byte[] out = new byte[hex.length() / 2];
        for (int i = 0; i < out.length; i++) {
            out[i] = (byte) Integer.parseInt(hex.substring(i * 2, i * 2 + 2), 16);
        }
        return out;
    }
}
