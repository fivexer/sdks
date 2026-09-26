package io.fivexer.sdk.model;

import java.util.List;

/** Envelope for {@code GET /v1/workers/{id}/time-corrections}: the whole trail for one person. */
public class TimeCorrectionList {

    private String workerId;
    private List<TimeCorrection> corrections;

    public String getWorkerId() { return workerId; }
    public List<TimeCorrection> getCorrections() { return corrections; }
}
