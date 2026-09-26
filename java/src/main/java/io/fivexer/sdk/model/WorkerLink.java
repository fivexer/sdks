package io.fivexer.sdk.model;

/**
 * A worker linked to the person they are in a connected system — a HubSpot owner, a Jira
 * account — so the connector writes back under them.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerLink {
    private String connector;
    private String vendorUserId;
    private String workerId;
    private String createdAt;

    /** Connector id: {@code hubspot}, {@code salesforce}, {@code pipedrive}, {@code jira}, … */
    public String getConnector() { return connector; }

    /** Their id in the connected system. */
    public String getVendorUserId() { return vendorUserId; }

    public String getWorkerId() { return workerId; }

    /** ISO-8601. */
    public String getCreatedAt() { return createdAt; }
}
