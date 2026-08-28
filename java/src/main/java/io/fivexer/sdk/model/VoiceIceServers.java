package io.fivexer.sdk.model;

import java.util.Collections;
import java.util.List;

/**
 * <strong>Experimental</strong> — see {@link VoiceIceServer}.
 *
 * <p>The answer to {@code voiceIce()} on either session plane.
 */
public class VoiceIceServers {

    private List<VoiceIceServer> iceServers;

    public List<VoiceIceServer> getIceServers() {
        return iceServers == null ? Collections.emptyList() : iceServers;
    }
}
