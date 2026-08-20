package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertTrue;

import java.io.IOException;
import java.nio.charset.StandardCharsets;
import java.nio.file.Files;
import java.nio.file.Path;
import java.nio.file.Paths;
import java.util.ArrayList;
import java.util.LinkedHashMap;
import java.util.List;
import java.util.Map;
import java.util.regex.Matcher;
import java.util.regex.Pattern;
import org.junit.jupiter.api.Test;

/**
 * Every operation in the contract catalogue is reachable from this SDK.
 *
 * <p>{@code contract/operations.yaml} is the shared checklist all four SDKs are held to. This
 * walks it and resolves each operation to a real method, so an endpoint added to the platform
 * cannot quietly go missing here — the failure names the exact operation.
 *
 * <p>Parsed with a small regex rather than a YAML library: the operation names are a flat list
 * of {@code - name: group.method} lines, and this keeps the SDK free of a test-only dependency.
 */
class ContractParityTest {

    private static final Pattern OPERATION = Pattern.compile("^\\s+- name: ([a-zA-Z][\\w.]*)$",
            Pattern.MULTILINE);

    /** Which class owns each operation prefix. The method name is the last dotted segment. */
    private static final Map<String, Class<?>> OWNERS = owners();

    /**
     * Catalogue operations this SDK does not implement yet.
     *
     * <p>Empty, and meant to stay that way: every operation in the shared catalogue resolves to a
     * real method here. It exists so a genuinely deliberate gap can be recorded rather than
     * hidden — an operation added to the catalogue and not to this list still fails the build.
     */
    private static final List<String> NOT_YET_IMPLEMENTED = List.of();

    private static Map<String, Class<?>> owners() {
        Map<String, Class<?>> map = new LinkedHashMap<>();
        map.put("tasks.context", TaskContexts.class);
        map.put("tasks.comments", TaskComments.class);
        map.put("tasks.attachments", TaskAttachments.class);
        map.put("tasks", Tasks.class);
        map.put("workers", Workers.class);
        map.put("skills", Skills.class);
        map.put("decisions", Decisions.class);
        map.put("workflows", Workflows.class);
        map.put("runs", Runs.class);
        map.put("learning", Learning.class);
        map.put("notifications.sequences", NotificationSequences.class);
        map.put("notifications.channels", NotificationChannels.class);
        map.put("history", History.class);
        map.put("team", Team.class);
        map.put("breaks", Breaks.class);
        map.put("teams", Teams.class);
        map.put("joinLinks", JoinLinks.class);
        map.put("identities", Identities.class);
        map.put("worker", FivexerWorker.class);
        map.put("supervisor", FivexerSupervisor.class);
        return map;
    }

    private static List<String> operations() throws IOException {
        Path catalogue = repoRoot().resolve("contract/operations.yaml");
        String text = Files.readString(catalogue, StandardCharsets.UTF_8);
        // Only the `groups:` section describes HTTP operations; `helpers:` below it lists the
        // non-HTTP surface (webhook verification, the quota snapshot), which has no method.
        String groups = text.split("\ngroups:", 2)[1].split("\nhelpers:", 2)[0];

        List<String> found = new ArrayList<>();
        Matcher matcher = OPERATION.matcher(groups);
        while (matcher.find()) {
            found.add(matcher.group(1));
        }
        return found;
    }

    /** The java/ module sits one level below the repo root that holds contract/. */
    private static Path repoRoot() {
        return Paths.get("").toAbsolutePath().getParent();
    }

    private static boolean hasMethod(Class<?> type, String name) {
        for (java.lang.reflect.Method method : type.getMethods()) {
            if (method.getName().equals(name)) {
                return true;
            }
        }
        return false;
    }

    @Test
    void every_contract_operation_resolves_to_a_method_on_this_sdk() throws IOException {
        List<String> operations = operations();
        // A silent regex miss would make the assertions below vacuously pass.
        assertTrue(operations.size() >= 80, "expected the full catalogue, found " + operations.size());

        List<String> missing = new ArrayList<>();
        for (String operation : operations) {
            if (NOT_YET_IMPLEMENTED.contains(operation)) {
                continue;
            }
            int lastDot = operation.lastIndexOf('.');
            // A dotless name is a top-level method on the client itself (stats, portal,
            // slaStats), not a group member. Special-casing only "stats" made the first new
            // one of these throw out of substring() instead of reporting a clean failure.
            if (lastDot < 0) {
                if (!hasMethod(Fivexer.class, operation)) {
                    missing.add(operation);
                }
                continue;
            }
            String prefix = operation.substring(0, lastDot);
            String method = operation.substring(lastDot + 1);
            Class<?> owner = OWNERS.get(prefix);
            if (owner == null || !hasMethod(owner, method)) {
                missing.add(operation);
            }
        }

        assertTrue(missing.isEmpty(), "operations missing from the Java SDK: " + missing);
    }

    @Test
    void every_resource_group_is_reachable_from_the_client() {
        Fivexer client = new Fivexer("https://api.fivexer.test", "sk_test");

        assertTrue(client.tasks() != null && client.tasks().context() != null);
        assertTrue(client.tasks().comments() != null && client.tasks().attachments() != null);
        assertTrue(client.workers() != null && client.skills() != null && client.decisions() != null);
        assertTrue(client.workflows() != null && client.runs() != null && client.learning() != null);
        assertTrue(client.notifications().sequences() != null);
        assertTrue(client.notifications().channels() != null);
        assertTrue(client.history() != null && client.team() != null && client.breaks() != null);
        assertTrue(client.getBaseUrl().equals("https://api.fivexer.test"));
    }
}
