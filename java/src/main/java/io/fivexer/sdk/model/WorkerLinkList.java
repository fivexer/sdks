package io.fivexer.sdk.model;

import java.util.List;

/** Envelope for {@code GET /v1/workers/links}. */
public class WorkerLinkList {

    private List<WorkerLink> links;

    public List<WorkerLink> getLinks() { return links; }
}
