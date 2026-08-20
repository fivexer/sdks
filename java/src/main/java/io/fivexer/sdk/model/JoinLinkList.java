package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for the join-link listing endpoint.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class JoinLinkList {
    private List<JoinLink> links;

    public List<JoinLink> getLinks() { return links; }
}
