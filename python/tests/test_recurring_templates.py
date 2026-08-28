"""Recurring templates: the standing tasks occurrences are cut from.

A template is created through `tasks.create` with a recurrence — there is no separate create —
and the only two operations against it are listing and stopping. It is never itself matchable
and never appears in `tasks.list()`, `tasks.scheduled()` or the queue stats, so a client that
wants to show a customer their standing work has to read this collection, not the task list.
"""

from __future__ import annotations

import asyncio

import pytest

from fivexer import FivexerApiError
from tests.conftest import empty_response, json_response, query_pairs

TEMPLATES = {
    "recurring": [
        {
            "id": "nightly-sweep",
            "tags": ["ops"],
            "priority": 80,
            "title": "Nightly sweep",
            "recurrence": {"everyMs": 86_400_000, "windowMs": 3_600_000, "onMiss": "park", "catchUp": "skip"},
            "nextAt": 1_756_080_000_000,
            "occurrences": 12,
        },
        {
            "id": "hourly-ping",
            "tags": ["ops", "monitoring"],
            "priority": None,
            "title": None,
            "recurrence": {"everyMs": 3_600_000},
            "nextAt": 1_756_003_600_000,
            "occurrences": 240,
        },
    ],
    "count": 2,
}


def test_list_reads_templates_with_their_clocks(server, client):
    server.set_response(json_response(200, TEMPLATES))

    templates = client.tasks.recurring.list()

    assert server.last.method == "GET"
    assert server.last.url.path == "/v1/tasks/recurring"
    assert [t.id for t in templates] == ["nightly-sweep", "hourly-ping"]
    first = templates[0]
    assert first.recurrence.every_ms == 86_400_000
    assert first.recurrence.window_ms == 3_600_000
    assert first.recurrence.on_miss == "park"
    # The clock is the point of the read: when the next occurrence opens, and how many have run.
    assert first.next_at == 1_756_080_000_000
    assert first.occurrences == 12


def test_a_template_without_a_priority_or_title_reads_them_as_none(server, client):
    server.set_response(json_response(200, TEMPLATES))

    template = client.tasks.recurring.list()[1]

    assert template.priority is None
    assert template.title is None
    assert template.recurrence.catch_up is None


def test_remove_stops_the_template_and_leaves_occurrences_alone_by_default(server, client):
    server.set_response(empty_response(204))

    client.tasks.recurring.remove("nightly-sweep")

    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/tasks/recurring/nightly-sweep"
    # Occurrences already cut are real scheduled tasks someone may be about to work. Sending
    # dropScheduled=false would look like a caller who considered them and declined; not sending
    # it says they never asked, which is the truth.
    assert query_pairs(server.last) == {}


def test_remove_can_drop_the_occurrences_already_materialized(server, client):
    server.set_response(empty_response(204))

    client.tasks.recurring.remove("nightly-sweep", drop_scheduled=True)

    assert query_pairs(server.last) == {"dropScheduled": "true"}


def test_a_template_id_with_a_slash_cannot_escape_the_path(server, client):
    server.set_response(empty_response(204))

    client.tasks.recurring.remove("tenant/nightly")

    assert server.last.url.raw_path.decode() == "/v1/tasks/recurring/tenant%2Fnightly"


def test_removing_an_unknown_template_raises_rather_than_succeeding_quietly(server, client):
    server.set_response(json_response(404, {"error": {"code": "not_found", "message": "recurring task"}}))

    with pytest.raises(FivexerApiError) as raised:
        client.tasks.recurring.remove("gone")

    # Removing a template twice is not idempotent here; swallowing the 404 would mask a wrong id.
    assert raised.value.status_code == 404
    assert raised.value.code == "not_found"


def test_the_async_client_reads_and_stops_templates_the_same_way(server, async_client):
    async def scenario() -> None:
        server.set_response(json_response(200, TEMPLATES))
        templates = await async_client.tasks.recurring.list()
        assert [t.id for t in templates] == ["nightly-sweep", "hourly-ping"]

        server.set_response(empty_response(204))
        await async_client.tasks.recurring.remove("hourly-ping", drop_scheduled=True)
        assert server.last.method == "DELETE"
        assert query_pairs(server.last) == {"dropScheduled": "true"}

    asyncio.run(scenario())
