package io.fivexer.sdk.model;

/**
 * SLO counters. {@code acceptanceRate} is null until at least one offer is recorded — 0.0 ("everyone missed") and null ("nothing measured") are different answers.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class SlaStats {
    private String tag;
    private Integer offers;
    private Integer acceptedInTime;
    private Integer acceptanceBreaches;
    private Integer completionBreaches;
    private Integer ttlExpiries;
    private Integer rejectionParked;
    private Integer scheduleMisses;
    private Double meanAcceptLatencyMs;
    private Double meanCompleteLatencyMs;
    private Double acceptanceRate;

    public String getTag() { return tag; }
    public Integer getOffers() { return offers; }
    public Integer getAcceptedInTime() { return acceptedInTime; }
    public Integer getAcceptanceBreaches() { return acceptanceBreaches; }
    public Integer getCompletionBreaches() { return completionBreaches; }
    public Integer getTtlExpiries() { return ttlExpiries; }
    public Integer getRejectionParked() { return rejectionParked; }
    public Integer getScheduleMisses() { return scheduleMisses; }
    public Double getMeanAcceptLatencyMs() { return meanAcceptLatencyMs; }
    public Double getMeanCompleteLatencyMs() { return meanCompleteLatencyMs; }
    public Double getAcceptanceRate() { return acceptanceRate; }
}
