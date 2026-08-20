package io.fivexer.sdk.model;

/**
 * Result of inviting a worker. {@code inviteUrl} is credential-equivalent until consumed; it is returned anyway because {@code emailStatus} is often {@code mailer_unconfigured}, which leaves sharing the link as the only delivery path.
 *
 * <p>Populated by Gson via field reflection and exposed read-only; callers receive instances
 * from the client and never construct them.
 */
public class WorkerInviteResult {
    private PublicWorkerIdentity identity;
    private Boolean workerCreated;
    private String emailStatus;
    private String inviteUrl;

    public PublicWorkerIdentity getIdentity() { return identity; }
    public Boolean getWorkerCreated() { return workerCreated; }
    public String getEmailStatus() { return emailStatus; }
    public String getInviteUrl() { return inviteUrl; }
}
