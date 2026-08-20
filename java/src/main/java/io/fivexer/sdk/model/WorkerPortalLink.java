package io.fivexer.sdk.model;

/**
 * Where workers log in, and whether the portal is switched on at all. A workspace with no published bundle is a normal state ({@code exists: false}), not an error.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerPortalLink {
    private Boolean portalEnabled;
    private String portalUrl;
    private Boolean exists;
    private Integer version;
    private String template;
    private String publishedAt;

    public Boolean getPortalEnabled() { return portalEnabled; }
    public String getPortalUrl() { return portalUrl; }
    public Boolean getExists() { return exists; }
    public Integer getVersion() { return version; }
    public String getTemplate() { return template; }
    public String getPublishedAt() { return publishedAt; }
}
