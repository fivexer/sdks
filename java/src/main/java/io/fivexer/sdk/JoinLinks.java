package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.CreateJoinLink;
import io.fivexer.sdk.model.CreateJoinLinkResult;
import io.fivexer.sdk.model.JoinLink;
import io.fivexer.sdk.model.JoinLinkList;
import java.util.Collections;
import java.util.List;

/** QR join links: worker self-registration with preset tags, skills and team. */
public final class JoinLinks {

    private final Fivexer client;

    JoinLinks(Fivexer client) {
        this.client = client;
    }

    /**
     * Create a link. The {@code joinUrl} on the result is returned only here — a lost link is
     * re-created, never recovered.
     */
    public CreateJoinLinkResult create(CreateJoinLink input) {
        return client.request("POST", "/join-links", input.toJson(), null,
                CreateJoinLinkResult.class, false);
    }

    public List<JoinLink> list() {
        JoinLinkList envelope = client.request("GET", "/join-links", null, null,
                JoinLinkList.class, false);
        return envelope.getLinks() == null ? Collections.emptyList() : envelope.getLinks();
    }

    public void revoke(String linkId) {
        client.request("DELETE", "/join-links/" + Json.enc(linkId), null, null, Void.class, true);
    }
}
