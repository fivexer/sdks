package io.fivexer.sdk.model;

import java.util.List;

/**
 * What happens when the worker a task was matched to lets the response deadline run out.
 *
 * <p>Without a policy the task is simply requeued after the workspace default and the same
 * worker may win it straight back — the failure mode {@code onNoResponse("block")} exists to
 * stop. Escalation owns the <em>response</em> clock only; the completion clock, shelf life and
 * rejection budget belong to {@link SlaPolicy}.
 *
 * <pre>{@code
 * new EscalationPolicy(60_000)
 *         .onNoResponse("block")
 *         .tiers(List.of(List.of("billing"), List.of("billing", "english")))
 *         .onExhausted("park");
 * }</pre>
 */
public class EscalationPolicy {

    private Long respondWithinMs;
    private String onNoResponse;
    private Double priorityBoost;
    private List<List<String>> tiers;
    private Integer maxEscalations;
    private String onExhausted;

    /** @param respondWithinMs milliseconds the matched worker has to respond (1s–24h) */
    public EscalationPolicy(long respondWithinMs) {
        this.respondWithinMs = respondWithinMs;
    }

    /** For Gson, which reads a policy back off a task. */
    EscalationPolicy() {}

    public Long getRespondWithinMs() { return respondWithinMs; }
    public String getOnNoResponse() { return onNoResponse; }
    public Double getPriorityBoost() { return priorityBoost; }
    public List<List<String>> getTiers() { return tiers; }
    public Integer getMaxEscalations() { return maxEscalations; }
    public String getOnExhausted() { return onExhausted; }

    /** {@code "block"} stops the non-responder winning it back; {@code "allow"} is the default. */
    public EscalationPolicy onNoResponse(String onNoResponse) { this.onNoResponse = onNoResponse; return this; }

    /** Added to the task's priority on every escalation, so an aging task outranks fresh work. */
    public EscalationPolicy priorityBoost(double priorityBoost) { this.priorityBoost = priorityBoost; return this; }

    /** Tag sets to widen to, one rung per escalation. */
    public EscalationPolicy tiers(List<List<String>> tiers) { this.tiers = tiers; return this; }

    public EscalationPolicy maxEscalations(int maxEscalations) { this.maxEscalations = maxEscalations; return this; }

    /** Where an exhausted ladder leaves the task: {@code "queue"} or {@code "park"}. */
    public EscalationPolicy onExhausted(String onExhausted) { this.onExhausted = onExhausted; return this; }
}
