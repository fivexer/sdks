package io.fivexer.sdk.model;

import java.util.Map;

/**
 * What a finished result must look like.
 *
 * <p>{@code schema} (JSON Schema 2020-12; no {@code pattern}/{@code patternProperties}, local
 * {@code $ref} only) is enforced at completion: a result that misses it is refused with a 422
 * {@code result_invalid}, unless it reports {@code success: false}. The keys {@code success},
 * {@code summary} and {@code timedOut} are set aside before the check. {@code acceptanceCriteria}
 * is shown to whoever does the work. Fixed at creation.
 *
 * <p>Serves as both an input model ({@link CreateTask#resultContract}) and a response model
 * ({@link TaskContext#getResultContract}).
 */
public class ResultContract {
    private Map<String, Object> schema;
    private String acceptanceCriteria;

    /** Response construction (Gson), or start empty and set what you need. */
    public ResultContract() {}

    public Map<String, Object> getSchema() { return schema; }
    public String getAcceptanceCriteria() { return acceptanceCriteria; }

    /** The JSON Schema the result must satisfy. */
    public ResultContract schema(Map<String, Object> schema) { this.schema = schema; return this; }

    /** Plain-language definition of done, shown to whoever does the work. */
    public ResultContract acceptanceCriteria(String acceptanceCriteria) {
        this.acceptanceCriteria = acceptanceCriteria;
        return this;
    }
}
