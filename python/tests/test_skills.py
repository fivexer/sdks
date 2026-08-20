"""Skill catalogue: defining skills and assigning them to workers."""

from __future__ import annotations

from fivexer import PatchSkill, UpsertSkill
from tests.conftest import empty_response, json_response, query_pairs, read_body

SKILL_BODY = {
    "id": "skl_1",
    "key": "refunds",
    "name": "Refunds",
    "description": "Handles refund requests",
    "createdAt": "2026-07-24T10:00:00.000Z",
}


def test_defining_a_skill_returns_the_stored_record(server, client):
    server.set_response(json_response(201, SKILL_BODY))

    skill = client.skills.create(
        UpsertSkill(key="refunds", name="Refunds", description="Handles refund requests")
    )

    assert server.last.url.path == "/v1/skills"
    assert (skill.id, skill.key, skill.name) == ("skl_1", "refunds", "Refunds")


def test_searching_the_catalogue_filters_by_query_and_limit(server, client):
    server.set_response(json_response(200, {"skills": [SKILL_BODY]}))

    skills = client.skills.list(q="ref", limit=10)

    assert query_pairs(server.last) == {"q": "ref", "limit": "10"}
    assert [s.key for s in skills] == ["refunds"]


def test_listing_the_whole_catalogue_sends_no_filters(server, client):
    server.set_response(json_response(200, {"skills": []}))

    assert client.skills.list() == []
    assert query_pairs(server.last) == {}


def test_renaming_a_skill_leaves_its_key_untouched(server, client):
    # The key is the stable identifier workers are matched on; only the label changes.
    server.set_response(json_response(200, {**SKILL_BODY, "name": "Refunds & credits"}))

    skill = client.skills.patch("skl_1", PatchSkill(name="Refunds & credits"))

    assert server.last.method == "PATCH"
    assert read_body(server.last) == {"name": "Refunds & credits"}
    assert skill.key == "refunds"


def test_removing_a_skill_returns_nothing(server, client):
    server.set_response(empty_response(204))

    assert client.skills.remove("skl_1") is None
    assert server.last.url.path == "/v1/skills/skl_1"


def test_suggesting_companions_for_the_skills_already_chosen(server, client):
    server.set_response(json_response(200, {"skills": [SKILL_BODY]}))

    suggested = client.skills.suggest(["billing", "english"], limit=5)

    assert query_pairs(server.last) == {"selected": "billing,english", "limit": "5"}
    assert [s.id for s in suggested] == ["skl_1"]


def test_suggesting_with_nothing_chosen_yet_asks_for_the_popular_ones(server, client):
    server.set_response(json_response(200, {"skills": []}))

    assert client.skills.suggest() == []
    assert query_pairs(server.last) == {}
