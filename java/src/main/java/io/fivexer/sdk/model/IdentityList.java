package io.fivexer.sdk.model;

import java.util.List;

/**
 * Envelope for the worker-identity listing endpoint.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class IdentityList {
    private List<PublicWorkerIdentity> identities;

    public List<PublicWorkerIdentity> getIdentities() { return identities; }
}
