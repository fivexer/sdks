import io.fivexer.sdk.Fivexer;
import io.fivexer.sdk.FivexerApiException;
import io.fivexer.sdk.model.CreateTask;
import java.util.List;

public class Errors {
    public static void main(String[] args) {
        Fivexer client = new Fivexer(
            System.getenv("FIVEXER_BASE_URL"),
            System.getenv("FIVEXER_API_KEY")
        );

        try {
            client.tasks().create(new CreateTask(List.of("english")));
        } catch (FivexerApiException e) {
            System.out.println("API error: " + e.getStatusCode() + " " + e.getCode());
            if (e.getRetryAfterSeconds() != null) {
                System.out.println("retry after: " + e.getRetryAfterSeconds());
            }
            if (e.getQuota() != null) {
                System.out.println("task rate remaining: " + e.getQuota().getTaskRateRemaining());
            }
        }
    }
}
