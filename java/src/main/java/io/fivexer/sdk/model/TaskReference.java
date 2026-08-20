package io.fivexer.sdk.model;

/**
 * An opaque pointer to data behind your own API — stored and displayed, never fetched by the
 * platform.
 *
 * <p>Serves as both a response model (Gson fills every field, {@code id} included) and an input
 * model: construct one with a url and the {@code id} stays null, which is omitted on the wire
 * because the API assigns it.
 */
public class TaskReference {
    private String id;
    private String url;
    private String label;
    private String contentType;

    /** Response construction (Gson). */
    public TaskReference() {}

    /** Input construction: the id is assigned by the API. */
    public TaskReference(String url) {
        if (url == null) {
            throw new IllegalArgumentException("url is required");
        }
        this.url = url;
    }

    public String getId() { return id; }
    public String getUrl() { return url; }
    public String getLabel() { return label; }
    public String getContentType() { return contentType; }

    public TaskReference label(String label) { this.label = label; return this; }
    public TaskReference contentType(String contentType) { this.contentType = contentType; return this; }
}
