package io.fivexer.sdk.model;

import java.util.List;

/**
 * <strong>Experimental — voice is not production-ready.</strong> This surface may change or be
 * withdrawn in a patch release; do not build on it yet.
 *
 * <p>One STUN/TURN server, shaped for a WebRTC {@code RTCIceServer}. Unlike the console's read
 * view this <em>does</em> carry {@code credential}: a worker or supervisor about to place a call
 * needs the TURN secret to authenticate to the relay, so the two reads differ deliberately.
 *
 * <p>{@code urls} is a string or a list of strings on the wire. Both forms are preserved:
 * {@link #getUrls()} always answers a list, and {@link #getUrlsRaw()} hands back what arrived.
 */
public class VoiceIceServer {

    private com.google.gson.JsonElement urls;
    private String username;
    private String credential;

    /** Every URL for this server, whether the wire sent one string or an array. */
    public List<String> getUrls() {
        if (urls == null || urls.isJsonNull()) {
            return java.util.Collections.emptyList();
        }
        if (urls.isJsonArray()) {
            List<String> out = new java.util.ArrayList<>();
            for (com.google.gson.JsonElement element : urls.getAsJsonArray()) {
                out.add(element.getAsString());
            }
            return java.util.Collections.unmodifiableList(out);
        }
        return java.util.Collections.singletonList(urls.getAsString());
    }

    /** The raw wire value, for a caller handing it straight to a WebRTC binding. */
    public com.google.gson.JsonElement getUrlsRaw() { return urls; }

    public String getUsername() { return username; }

    /** The TURN shared secret. Present on the session planes, withheld from the console read. */
    public String getCredential() { return credential; }
}
