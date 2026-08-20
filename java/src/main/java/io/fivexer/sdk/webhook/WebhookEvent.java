package io.fivexer.sdk.webhook;

import com.google.gson.Gson;
import com.google.gson.JsonElement;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import io.fivexer.sdk.FivexerException;
import java.nio.charset.StandardCharsets;
import java.util.Map;

/**
 * A verified webhook payload. {@link #getEvent()} is the event type (e.g. {@code task.matched})
 * when the payload carried one; {@link #getData()} is the full parsed JSON object.
 */
public class WebhookEvent {

    private static final Gson GSON = new Gson();

    private final String event;
    private final Map<String, Object> data;

    public WebhookEvent(String event, Map<String, Object> data) {
        this.event = event;
        this.data = data;
    }

    public String getEvent() { return event; }

    @SuppressWarnings("unchecked")
    public Map<String, Object> getData() { return data; }

    @SuppressWarnings("unchecked")
    static WebhookEvent fromPayload(byte[] payload) {
        try {
            JsonElement root = JsonParser.parseString(new String(payload, StandardCharsets.UTF_8));
            if (!root.isJsonObject()) {
                return new WebhookEvent(null, null);
            }
            JsonObject obj = root.getAsJsonObject();
            Map<String, Object> map = GSON.fromJson(obj, Map.class);
            String event = obj.has("event") && obj.get("event").isJsonPrimitive()
                    ? obj.get("event").getAsString() : null;
            return new WebhookEvent(event, map);
        } catch (Exception e) {
            throw new FivexerException("webhook payload is not valid JSON", e);
        }
    }
}
