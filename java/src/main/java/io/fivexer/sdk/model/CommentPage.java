package io.fivexer.sdk.model;

import java.util.List;

/**
 * A cursor-paginated page of comments.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class CommentPage {
    private List<Comment> comments;
    private String nextCursor;
    private boolean hasMore;

    public List<Comment> getComments() { return comments; }
    public String getNextCursor() { return nextCursor; }
    public boolean isHasMore() { return hasMore; }
}
