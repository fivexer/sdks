package io.fivexer.sdk.model;

import io.fivexer.sdk.internal.Json;

/**
 * Which clocks to reset when returning a parked task to the queue. Leave a clock set and the next sweep may park the task straight back.
 *
 * <p>Fluent builder: set what you need, then hand it to the client. {@link #toJson()} omits
 * every field that was never set, because the API treats an absent field as "leave unchanged"
 * and an explicit null as "clear it".
 */
public class UnparkTask {
    private Boolean resetEscalation;
    private Boolean resetSla;
    private Boolean resetSchedule;

    public UnparkTask() {}

    public Boolean getResetEscalation() { return resetEscalation; }
    public Boolean getResetSla() { return resetSla; }
    public Boolean getResetSchedule() { return resetSchedule; }

    public UnparkTask resetEscalation(Boolean resetEscalation) { this.resetEscalation = resetEscalation; return this; }
    public UnparkTask resetSla(Boolean resetSla) { this.resetSla = resetSla; return this; }
    public UnparkTask resetSchedule(Boolean resetSchedule) { this.resetSchedule = resetSchedule; return this; }

    /** Serialise for the wire, omitting unset fields. */
    public String toJson() {
        return Json.write(this);
    }
}
