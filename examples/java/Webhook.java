import io.fivexer.sdk.webhook.Webhook;
import io.fivexer.sdk.webhook.WebhookEvent;

public class Webhook {
    public static void main(String[] args) {
        byte[] rawBody = ("{\"event\":\"task.matched\",\"data\":{\"taskId\":\"task_8fk2\",\"workerId\":\"agent_1\"}}")
            .getBytes(java.nio.charset.StandardCharsets.UTF_8);
        String secret = "whsec_test_secret";
        String header = "t=1704067200000,v1=7f3b9c2e4d8a1f6e5b0c3d7a9e2f4b8c1d5e6a7f9b0c2d3e4f5a6b7c8d9e0f1a";

        WebhookEvent event = Webhook.constructEvent(rawBody, header, secret);
        System.out.println("verified event: " + event.getEvent());
    }
}
