package io.fivexer.sdk.internal;

import com.google.gson.Gson;
import com.google.gson.JsonObject;
import com.google.gson.JsonParser;
import java.util.LinkedHashMap;
import java.util.Map;

/**
 * Shared JSON and URL helpers for request building.
 *
 * <p>Every input model serialises through {@link #write(Object)}. Gson omits null fields by
 * default, which is exactly the wire rule the API needs: an <em>absent</em> field keeps its
 * stored value on a partial update, while an explicit {@code null} would clear it. Routing all
 * input models through one call means that rule is implemented — and tested — in one place
 * instead of once per optional field.
 */
public final class Json {

    private static final Gson GSON = new Gson();

    private Json() {}

    /** Serialise an input model, omitting every field that was never set. */
    public static String write(Object input) {
        return GSON.toJson(input);
    }

    /** Serialise an arbitrary value (maps, lists) into a JSON tree for embedding. */
    public static com.google.gson.JsonElement tree(Object value) {
        return JsonParser.parseString(GSON.toJson(value));
    }

    /** A one-field JSON object, for the many endpoints whose body is a single value. */
    public static String object(String key, Object value) {
        JsonObject o = new JsonObject();
        o.add(key, tree(value));
        return o.toString();
    }

    /**
     * {@code {"workerId": ...}} plus an optional result payload — the shape shared by the task
     * accept/reject/complete endpoints on both the workspace and the portal plane.
     */
    public static String workerAction(String workerId, Map<String, Object> result) {
        JsonObject o = new JsonObject();
        o.addProperty("workerId", workerId);
        if (result != null) {
            o.add("result", tree(result));
        }
        return o.toString();
    }

    /** Percent-encode one path segment so an id containing {@code /} cannot escape it. */
    public static String enc(String value) {
        return java.net.URLEncoder.encode(value, java.nio.charset.StandardCharsets.UTF_8).replace("+", "%20");
    }

    /**
     * Build a query map from alternating key/value pairs, dropping pairs whose value is null.
     * Returns null when nothing was set, so the caller sends no query string at all.
     */
    public static Map<String, String> query(String... keyValues) {
        Map<String, String> m = new LinkedHashMap<>();
        for (int i = 0; i + 1 < keyValues.length; i += 2) {
            if (keyValues[i + 1] != null) {
                m.put(keyValues[i], keyValues[i + 1]);
            }
        }
        return m.isEmpty() ? null : m;
    }

    /** Render a number for a query string, or null when it was not set. */
    public static String str(Number value) {
        return value == null ? null : String.valueOf(value);
    }
}
