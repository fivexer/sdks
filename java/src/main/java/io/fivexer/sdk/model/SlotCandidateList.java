package io.fivexer.sdk.model;

import java.util.List;

/** Envelope for {@code GET /v1/tasks/{id}/booking/candidates}. */
public class SlotCandidateList {

    private List<SlotCandidate> candidates;
    private int count;

    public List<SlotCandidate> getCandidates() { return candidates; }
    public int getCount() { return count; }
}
