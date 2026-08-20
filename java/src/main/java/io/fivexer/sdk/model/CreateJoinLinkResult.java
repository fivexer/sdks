package io.fivexer.sdk.model;

/**
 * A new join link plus its URL. The URL is returned only here — a lost link is re-created, never recovered.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class CreateJoinLinkResult {
    private JoinLink link;
    private String joinUrl;

    public JoinLink getLink() { return link; }
    public String getJoinUrl() { return joinUrl; }
}
