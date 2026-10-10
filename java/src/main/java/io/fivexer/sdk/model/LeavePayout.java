package io.fivexer.sdk.model;

/**
 * Leave settled when a worker leaves: minutes paid out, and minutes recovered (taken beyond what
 * was earned).
 *
 * <p>Populated by Gson via field reflection and exposed read-only.
 */
public class LeavePayout {
    private int paidOutMinutes;
    private int recoveredMinutes;

    public int getPaidOutMinutes() { return paidOutMinutes; }
    public int getRecoveredMinutes() { return recoveredMinutes; }
}
