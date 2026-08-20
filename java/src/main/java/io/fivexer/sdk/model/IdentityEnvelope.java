package io.fivexer.sdk.model;

/**
 * Single-identity envelope returned by the identity create/update endpoints.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class IdentityEnvelope {
    private PublicWorkerIdentity identity;

    public PublicWorkerIdentity getIdentity() { return identity; }
}
