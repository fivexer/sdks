package io.fivexer.sdk;

import io.fivexer.sdk.internal.Json;
import io.fivexer.sdk.model.CreateWorkerIdentity;
import io.fivexer.sdk.model.IdentityEnvelope;
import io.fivexer.sdk.model.IdentityList;
import io.fivexer.sdk.model.InviteWorkerIdentity;
import io.fivexer.sdk.model.PublicWorkerIdentity;
import io.fivexer.sdk.model.UpdateWorkerIdentity;
import io.fivexer.sdk.model.WorkerInviteResult;
import java.util.Collections;
import java.util.List;

/**
 * Worker portal credentials.
 *
 * <p>A worker (a routing target) and an identity (a way to sign in) are separate things:
 * {@link #invite} creates both when the worker does not exist yet.
 */
public final class Identities {

    private final Fivexer client;

    Identities(Fivexer client) {
        this.client = client;
    }

    public List<PublicWorkerIdentity> list() {
        IdentityList envelope = client.request("GET", "/worker-identities", null, null,
                IdentityList.class, false);
        return envelope.getIdentities() == null ? Collections.emptyList() : envelope.getIdentities();
    }

    /**
     * Email a set-your-PIN link. Check {@code emailStatus} on the result: {@code
     * mailer_unconfigured} means the deployment cannot send mail, and {@code inviteUrl} is then
     * the only way to deliver it.
     */
    public WorkerInviteResult invite(InviteWorkerIdentity input) {
        return client.request("POST", "/worker-identities/invite", input.toJson(), null,
                WorkerInviteResult.class, false);
    }

    public WorkerInviteResult resendInvite(String workerId) {
        return client.request("POST",
                "/worker-identities/" + Json.enc(workerId) + "/invite/resend", null, null,
                WorkerInviteResult.class, false);
    }

    /** Set a PIN directly, for a worker who will never receive email (a kiosk, an agent). */
    public PublicWorkerIdentity create(String workerId, CreateWorkerIdentity input) {
        IdentityEnvelope envelope = client.request("POST",
                "/workers/" + Json.enc(workerId) + "/identity", input.toJson(), null,
                IdentityEnvelope.class, false);
        return envelope.getIdentity();
    }

    public PublicWorkerIdentity update(String workerId, UpdateWorkerIdentity patch) {
        IdentityEnvelope envelope = client.request("PATCH",
                "/workers/" + Json.enc(workerId) + "/identity", patch.toJson(), null,
                IdentityEnvelope.class, false);
        return envelope.getIdentity();
    }

    /** Removes the sign-in, not the worker — they stay routable. */
    public void remove(String workerId) {
        client.request("DELETE", "/workers/" + Json.enc(workerId) + "/identity", null, null,
                Void.class, true);
    }
}
