package io.fivexer.sdk;

import static org.junit.jupiter.api.Assertions.assertEquals;
import static org.junit.jupiter.api.Assertions.assertNull;
import static org.junit.jupiter.api.Assertions.assertThrows;
import static org.junit.jupiter.api.Assertions.assertTrue;

import io.fivexer.sdk.model.RecurringTask;
import java.util.List;
import okhttp3.mockwebserver.RecordedRequest;
import org.junit.jupiter.api.Test;

/**
 * Recurring templates: the standing tasks occurrences are cut from.
 *
 * <p>A template is created through {@code tasks().create()} with a recurrence — there is no
 * separate create — and the only two operations against it are listing and stopping. It is never
 * itself matchable and never appears in {@code tasks().list()}, {@code tasks().scheduled()} or
 * the queue stats, so a client showing someone their standing work has to read this collection
 * rather than the task list.
 */
class RecurringTemplatesTest extends MockServerBase {

    private static final String TEMPLATES = "{\"recurring\":["
            + "{\"id\":\"nightly-sweep\",\"tags\":[\"ops\"],\"priority\":80,\"title\":\"Nightly sweep\","
            + "\"recurrence\":{\"everyMs\":86400000,\"windowMs\":3600000,\"onMiss\":\"park\","
            + "\"catchUp\":\"skip\"},\"nextAt\":1756080000000,\"occurrences\":12},"
            + "{\"id\":\"hourly-ping\",\"tags\":[\"ops\",\"monitoring\"],\"priority\":null,\"title\":null,"
            + "\"recurrence\":{\"everyMs\":3600000},\"nextAt\":1756003600000,\"occurrences\":240}"
            + "],\"count\":2}";

    @Test
    void listing_reads_templates_with_their_clocks() throws Exception {
        enqueueJson(200, TEMPLATES);

        List<RecurringTask> templates = client().tasks().recurring().list();

        RecordedRequest request = takeRequest();
        assertEquals("GET", request.getMethod());
        assertEquals("/v1/tasks/recurring", request.getPath());
        assertEquals(2, templates.size());
        RecurringTask first = templates.get(0);
        assertEquals("nightly-sweep", first.getId());
        assertEquals(86_400_000, first.getRecurrence().getEveryMs());
        assertEquals("park", first.getRecurrence().getOnMiss());
        // The clock is the point of the read: when the next occurrence opens, how many have run.
        assertEquals(1_756_080_000_000L, first.getNextAt());
        assertEquals(12, first.getOccurrences());
    }

    @Test
    void a_template_without_a_priority_or_title_reads_them_as_null() throws Exception {
        enqueueJson(200, TEMPLATES);

        RecurringTask template = client().tasks().recurring().list().get(1);

        assertNull(template.getPriority());
        assertNull(template.getTitle());
        assertNull(template.getRecurrence().getCatchUp());
    }

    @Test
    void an_empty_collection_reads_as_an_empty_list_not_a_null() throws Exception {
        enqueueJson(200, "{\"count\":0}");

        // A workspace with no templates must not make a caller null-check before iterating.
        assertTrue(client().tasks().recurring().list().isEmpty());
    }

    @Test
    void removing_a_template_leaves_its_occurrences_alone_by_default() throws Exception {
        enqueueEmpty(204);

        client().tasks().recurring().remove("nightly-sweep");

        RecordedRequest request = takeRequest();
        assertEquals("DELETE", request.getMethod());
        assertEquals("/v1/tasks/recurring/nightly-sweep", pathOf(request));
        // Occurrences already cut are real scheduled tasks someone may be about to work. A
        // dropScheduled=false on the wire would look like a caller who considered them and
        // declined; sending nothing says they never asked, which is the truth.
        assertEquals("", queryOf(request));
    }

    @Test
    void removing_can_also_drop_the_occurrences_already_materialized() throws Exception {
        enqueueEmpty(204);

        client().tasks().recurring().remove("nightly-sweep", true);

        assertEquals("dropScheduled=true", queryOf(takeRequest()));
    }

    @Test
    void a_template_id_with_a_slash_cannot_escape_the_path() throws Exception {
        enqueueEmpty(204);

        client().tasks().recurring().remove("tenant/nightly");

        assertEquals("/v1/tasks/recurring/tenant%2Fnightly", pathOf(takeRequest()));
    }

    @Test
    void removing_an_unknown_template_raises_rather_than_succeeding_quietly() throws Exception {
        enqueueJson(404, "{\"error\":{\"code\":\"not_found\",\"message\":\"recurring task\"}}");

        FivexerApiException error = assertThrows(FivexerApiException.class,
                () -> client().tasks().recurring().remove("gone"));

        // Removing a template twice is not idempotent; swallowing the 404 would mask a wrong id.
        assertEquals(404, error.getStatusCode());
        assertEquals("not_found", error.getCode());
    }
}
