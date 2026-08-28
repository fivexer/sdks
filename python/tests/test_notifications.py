"""Notification sequences (when to notify) and channels (where to deliver)."""

from __future__ import annotations

from fivexer import (
    CreateNotificationChannel,
    CreateNotificationSequence,
    NotificationSequenceStep,
    UpdateNotificationChannel,
    UpdateNotificationSequence,
)
from tests.conftest import empty_response, json_response, read_body

SEQUENCE_BODY = {
    "id": "seq_1",
    "name": "Escalate stale tasks",
    "enabled": True,
    "steps": [{"trigger": "matched", "offsetMs": 300000, "eventType": "task.expiring"}],
    "filterTags": ["billing"],
    "createdAt": "2026-07-24T10:00:00.000Z",
    "updatedAt": "2026-07-24T10:00:00.000Z",
}

CHANNEL_BODY = {
    "id": "ch_1",
    "type": "webhook",
    "target": "https://hooks.example/fivexer",
    "events": ["task.matched", "task.completed"],
    "disabled": False,
    "createdAt": "2026-07-24T10:00:00.000Z",
}


# ---- sequences ------------------------------------------------------------


def test_listing_sequences_unwraps_the_envelope(server, client):
    server.set_response(json_response(200, {"sequences": [SEQUENCE_BODY]}))

    sequences = client.notifications.sequences.list()

    assert [s.id for s in sequences] == ["seq_1"]
    assert sequences[0].steps[0].offset_ms == 300000


def test_creating_a_sequence_schedules_its_steps_relative_to_a_trigger(server, client):
    server.set_response(json_response(201, SEQUENCE_BODY))

    sequence = client.notifications.sequences.create(
        CreateNotificationSequence(
            name="Escalate stale tasks",
            steps=[NotificationSequenceStep(trigger="matched", offset_ms=300000, event_type="task.expiring")],
            filter_tags=["billing"],
        )
    )

    assert read_body(server.last) == {
        "name": "Escalate stale tasks",
        "steps": [{"trigger": "matched", "offsetMs": 300000, "eventType": "task.expiring"}],
        "filterTags": ["billing"],
    }
    assert sequence.enabled is True
    assert sequence.filter_tags == ["billing"]


def test_reading_a_sequence_by_id(server, client):
    server.set_response(json_response(200, SEQUENCE_BODY))

    assert client.notifications.sequences.get("seq_1").name == "Escalate stale tasks"
    assert server.last.url.path == "/v1/notification-sequences/seq_1"


def test_disabling_a_sequence_leaves_its_steps_intact(server, client):
    # A PATCH with only `enabled` must not blank the steps it does not mention.
    server.set_response(json_response(200, {**SEQUENCE_BODY, "enabled": False}))

    sequence = client.notifications.sequences.update("seq_1", UpdateNotificationSequence(enabled=False))

    assert read_body(server.last) == {"enabled": False}
    assert sequence.enabled is False
    assert len(sequence.steps) == 1


def test_removing_a_sequence_returns_nothing(server, client):
    server.set_response(empty_response(204))

    assert client.notifications.sequences.remove("seq_1") is None


# ---- channels -------------------------------------------------------------


def test_listing_channels_unwraps_the_envelope(server, client):
    server.set_response(json_response(200, {"channels": [CHANNEL_BODY]}))

    assert [c.id for c in client.notifications.channels.list()] == ["ch_1"]


def test_creating_a_webhook_channel_sends_the_signing_secret(server, client):
    server.set_response(json_response(201, CHANNEL_BODY))

    channel = client.notifications.channels.create(
        CreateNotificationChannel(
            type="webhook",
            target="https://hooks.example/fivexer",
            events=["task.matched", "task.completed"],
            secret="whsec_abc",
        )
    )

    assert read_body(server.last)["secret"] == "whsec_abc"
    # …and the response never echoes it back.
    assert not hasattr(channel, "secret")
    assert channel.events == ["task.matched", "task.completed"]


def test_reading_a_channel_by_id(server, client):
    server.set_response(json_response(200, CHANNEL_BODY))

    assert client.notifications.channels.get("ch_1").target == "https://hooks.example/fivexer"


def test_disabling_a_channel_stops_delivery_without_deleting_it(server, client):
    server.set_response(json_response(200, {**CHANNEL_BODY, "disabled": True}))

    channel = client.notifications.channels.update("ch_1", UpdateNotificationChannel(disabled=True))

    assert read_body(server.last) == {"disabled": True}
    assert channel.disabled is True
    assert channel.id == "ch_1"


def test_rotating_a_channel_secret_touches_nothing_else(server, client):
    server.set_response(json_response(200, CHANNEL_BODY))

    client.notifications.channels.update("ch_1", UpdateNotificationChannel(secret="whsec_new"))

    assert read_body(server.last) == {"secret": "whsec_new"}


def test_removing_a_channel_returns_nothing(server, client):
    server.set_response(empty_response(204))

    assert client.notifications.channels.remove("ch_1") is None
    assert server.last.method == "DELETE"
