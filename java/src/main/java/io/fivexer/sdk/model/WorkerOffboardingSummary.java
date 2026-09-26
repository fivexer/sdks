package io.fivexer.sdk.model;

import java.util.List;

/**
 * What removing a worker would hand back and free. Read-only: show it before
 * {@code workers().remove(...)}.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerOffboardingSummary {
    private String workerId;
    private String lastDay;
    private TaskCounts tasks;
    private List<RosterShifts> rosters;
    private int coverRequests;
    private int coverOffers;
    private int swaps;
    private int pendingTimeOff;
    private int leavePolicies;
    private int teams;

    public String getWorkerId() { return workerId; }

    /** Their last working day ({@code YYYY-MM-DD}). Shifts after it are freed. */
    public String getLastDay() { return lastDay; }

    /** Work that goes back to the queue. */
    public TaskCounts getTasks() { return tasks; }

    /** Rosters with shifts after {@code lastDay} naming them. */
    public List<RosterShifts> getRosters() { return rosters; }

    public int getCoverRequests() { return coverRequests; }
    public int getCoverOffers() { return coverOffers; }
    public int getSwaps() { return swaps; }
    public int getPendingTimeOff() { return pendingTimeOff; }
    public int getLeavePolicies() { return leavePolicies; }
    public int getTeams() { return teams; }

    /** Held work by state. */
    public static class TaskCounts {
        private int pending;
        private int accepted;
        private int booked;

        public int getPending() { return pending; }
        public int getAccepted() { return accepted; }

        /** Appointment slots reserved for them. */
        public int getBooked() { return booked; }
    }

    /** One roster and how many of its shifts after the last day name this worker. */
    public static class RosterShifts {
        private String rosterId;
        private String name;
        private boolean published;
        private int shifts;

        public String getRosterId() { return rosterId; }
        public String getName() { return name; }
        public boolean isPublished() { return published; }
        public int getShifts() { return shifts; }
    }
}
