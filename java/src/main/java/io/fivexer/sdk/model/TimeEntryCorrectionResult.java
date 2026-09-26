package io.fivexer.sdk.model;

import java.util.List;

/**
 * The result of adding or correcting a time entry: the record as it now reads and the trail
 * entry that explains the change.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class TimeEntryCorrectionResult {
    private CorrectedTimeEntry entry;
    private TimeCorrection correction;
    private List<CorrectedTimeEntry> breaks;
    private List<TimeCorrection> corrections;

    public CorrectedTimeEntry getEntry() { return entry; }

    /** The shift's own trail entry. */
    public TimeCorrection getCorrection() { return correction; }

    /** Present when the shift was saved with its breaks: every break it holds afterwards. */
    public List<CorrectedTimeEntry> getBreaks() { return breaks; }

    /** Present with {@link #getBreaks()}: one trail entry per record touched, the shift's first. */
    public List<TimeCorrection> getCorrections() { return corrections; }
}
