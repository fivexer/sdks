import io.fivexer.sdk.Fivexer;
import io.fivexer.sdk.model.CreateTask;
import io.fivexer.sdk.model.Decision;
import io.fivexer.sdk.model.ListDecisionsQuery;
import io.fivexer.sdk.model.UpsertWorker;
import java.util.List;

public class Quickstart {
    public static void main(String[] args) {
        Fivexer client = new Fivexer(
            System.getenv("FIVEXER_BASE_URL"),
            System.getenv("FIVEXER_API_KEY")
        );

        String workerId = client.workers().upsert(
            new UpsertWorker("agent_1").tags(List.of("english", "billing"))
        );
        System.out.println("upserted worker: " + workerId);

        var task = client.tasks().create(
            new CreateTask(List.of("english", "billing")).priority(90)
        );
        System.out.println("created task: " + task.getId() + " (" + task.getStatus() + ")");

        var queue = client.workers().queue(workerId);
        System.out.println("queue size: " + queue.getTaskIds().size());

        List<Decision> decisions = client.decisions().list(
            new ListDecisionsQuery().taskId(task.getId()).limit(10)
        );
        for (Decision d : decisions) {
            System.out.println("decision: worker=" + d.getWorkerId() + " status=" + d.getStatus());
        }
    }
}
