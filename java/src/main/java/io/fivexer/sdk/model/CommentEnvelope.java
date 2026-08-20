package io.fivexer.sdk.model;

/**
 * Envelope for the single-comment responses.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class CommentEnvelope {
    private Comment comment;

    public Comment getComment() { return comment; }
}
