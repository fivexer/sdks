"""Task context, comments and attachments — the rich-data surface around a task."""

from __future__ import annotations

import httpx
import pytest

from fivexer import (
    AddComment,
    CreateAttachment,
    FivexerApiError,
    SetTaskContext,
    TaskReferenceInput,
)
from tests.conftest import empty_response, json_response, query_pairs, read_body

CONTEXT_BODY = {
    "taskId": "task_8fk2",
    "title": "Refund request",
    "description": "Customer wants a refund for order 41",
    "context": {"orderId": "41"},
    "references": [{"id": "ref_1", "url": "https://crm/o/41", "label": "Order 41"}],
    "createdAt": 1750000000000,
    "updatedAt": 1750000900000,
}

ATTACHMENT_BODY = {
    "id": "att_1",
    "taskId": "task_8fk2",
    "filename": "receipt.pdf",
    "contentType": "application/pdf",
    "sizeBytes": 6,
    "status": "pending",
    "uploader": {"type": "api", "id": None},
    "createdAt": 1750001000000,
    "confirmedAt": None,
}


# ---- context --------------------------------------------------------------


def test_reading_the_context_of_a_task_returns_its_references(server, client):
    server.set_response(json_response(200, CONTEXT_BODY))

    context = client.tasks.context.get("task_8fk2")

    assert server.last.url.path == "/v1/tasks/task_8fk2/context"
    assert context.title == "Refund request"
    assert context.references[0].url == "https://crm/o/41"


def test_setting_context_replaces_it_wholesale(server, client):
    server.set_response(json_response(200, CONTEXT_BODY))

    client.tasks.context.set(
        "task_8fk2",
        SetTaskContext(title="Refund request", references=[TaskReferenceInput(url="https://crm/o/41")]),
    )

    assert server.last.method == "PUT"
    assert read_body(server.last) == {
        "title": "Refund request",
        "references": [{"url": "https://crm/o/41"}],
    }


def test_clearing_context_returns_nothing(server, client):
    server.set_response(empty_response(204))

    assert client.tasks.context.clear("task_8fk2") is None
    assert server.last.method == "DELETE"


# ---- comments -------------------------------------------------------------


def test_adding_a_comment_returns_the_stored_comment_not_the_envelope(server, client):
    server.set_response(
        json_response(
            201,
            {
                "comment": {
                    "id": "cmt_1",
                    "taskId": "task_8fk2",
                    "author": {"type": "worker", "id": "agent_1", "label": "Ada"},
                    "body": "Called the customer back",
                    "createdAt": 1750001000000,
                }
            },
        )
    )

    comment = client.tasks.comments.add(
        "task_8fk2", AddComment(body="Called the customer back", worker_id="agent_1")
    )

    assert comment.id == "cmt_1"
    assert comment.author.label == "Ada"


def test_listing_comments_reports_the_cursor_for_the_next_page(server, client):
    server.set_response(
        json_response(
            200,
            {
                "comments": [
                    {
                        "id": "cmt_1",
                        "taskId": "task_8fk2",
                        "author": {"type": "api", "id": None, "label": None},
                        "body": "Escalated",
                        "createdAt": 1,
                    }
                ],
                "nextCursor": "cursor_c1",
                "hasMore": True,
            },
        )
    )

    page = client.tasks.comments.list("task_8fk2", limit=1)

    assert query_pairs(server.last) == {"limit": "1"}
    assert page.has_more is True
    assert page.next_cursor == "cursor_c1"
    assert page.comments[0].body == "Escalated"


def test_removing_a_comment_targets_it_by_id(server, client):
    server.set_response(empty_response(204))

    client.tasks.comments.remove("task_8fk2", "cmt_1")

    assert server.last.method == "DELETE"
    assert server.last.url.path == "/v1/tasks/task_8fk2/comments/cmt_1"


# ---- attachments ----------------------------------------------------------


def test_creating_an_attachment_returns_where_to_put_the_bytes(server, client):
    server.set_response(
        json_response(
            201,
            {
                "attachment": ATTACHMENT_BODY,
                "upload": {
                    "url": "https://storage.test/att_1?sig=abc",
                    "method": "PUT",
                    "headers": {"content-type": "application/pdf"},
                    "expiresAt": 1750004600000,
                },
            },
        )
    )

    created = client.tasks.attachments.create(
        "task_8fk2", CreateAttachment(filename="receipt.pdf", content_type="application/pdf", size_bytes=6)
    )

    assert created.attachment.status == "pending"
    assert created.upload.url == "https://storage.test/att_1?sig=abc"
    assert created.upload.headers == {"content-type": "application/pdf"}


def test_listing_attachments_unwraps_the_envelope(server, client):
    server.set_response(json_response(200, {"attachments": [ATTACHMENT_BODY]}))

    attachments = client.tasks.attachments.list("task_8fk2")

    assert [a.id for a in attachments] == ["att_1"]


def test_listing_attachments_of_a_task_with_none_returns_an_empty_list(server, client):
    server.set_response(json_response(200, {"attachments": []}))

    assert client.tasks.attachments.list("task_8fk2") == []


def test_downloading_an_attachment_returns_a_short_lived_url(server, client):
    server.set_response(json_response(200, {"url": "https://storage.test/dl", "expiresAt": 1750004600000}))

    download = client.tasks.attachments.download("task_8fk2", "att_1")

    assert download.url == "https://storage.test/dl"
    assert download.expires_at == 1750004600000


def test_removing_an_attachment_targets_it_by_id(server, client):
    server.set_response(empty_response(204))

    client.tasks.attachments.remove("task_8fk2", "att_1")

    assert server.last.url.path == "/v1/tasks/task_8fk2/attachments/att_1"


def test_confirming_an_attachment_marks_it_ready(server, client):
    server.set_response(
        json_response(200, {"attachment": {**ATTACHMENT_BODY, "status": "ready", "confirmedAt": 2}})
    )

    attachment = client.tasks.attachments.confirm("task_8fk2", "att_1")

    assert attachment.status == "ready"
    assert attachment.confirmed_at == 2


# ---- the upload helper: create -> PUT to storage -> confirm ----------------


def _upload_routes(storage_status: int = 200):
    """Route the three hops of an upload: /v1 create, the storage PUT, /v1 confirm."""
    calls: list[httpx.Request] = []

    def handler(request: httpx.Request) -> httpx.Response:
        calls.append(request)
        if request.url.host == "storage.test":
            return httpx.Response(storage_status)
        if request.url.path.endswith("/confirm"):
            return json_response(200, {"attachment": {**ATTACHMENT_BODY, "status": "ready"}})
        return json_response(
            201,
            {
                "attachment": ATTACHMENT_BODY,
                "upload": {
                    "url": "https://storage.test/att_1?sig=abc",
                    "method": "PUT",
                    "headers": {"content-type": "application/pdf", "x-amz-meta-task": "task_8fk2"},
                    "expiresAt": 1,
                },
            },
        )

    return handler, calls


def test_uploading_a_file_reserves_stores_and_confirms_it_in_one_call(server, client):
    handler, calls = _upload_routes()
    server.set_responder(handler)

    attachment = client.tasks.attachments.upload(
        "task_8fk2", b"hello!", filename="receipt.pdf", content_type="application/pdf"
    )

    assert attachment.status == "ready"
    assert [c.url.path for c in calls] == [
        "/v1/tasks/task_8fk2/attachments",
        "/att_1",
        "/v1/tasks/task_8fk2/attachments/att_1/confirm",
    ]


def test_uploading_derives_the_size_from_the_payload(server, client):
    handler, calls = _upload_routes()
    server.set_responder(handler)

    client.tasks.attachments.upload(
        "task_8fk2", b"hello!", filename="receipt.pdf", content_type="application/pdf"
    )

    assert read_body(calls[0])["sizeBytes"] == 6


def test_uploading_sends_the_presigned_headers_verbatim(server, client):
    # The headers are part of the signature: altering or dropping one makes storage reject it.
    handler, calls = _upload_routes()
    server.set_responder(handler)

    client.tasks.attachments.upload(
        "task_8fk2", b"hello!", filename="receipt.pdf", content_type="application/pdf"
    )

    storage_put = calls[1]
    assert storage_put.method == "PUT"
    assert storage_put.headers["x-amz-meta-task"] == "task_8fk2"
    assert storage_put.content == b"hello!"


def test_uploading_does_not_send_the_api_key_to_object_storage(server, client):
    handler, calls = _upload_routes()
    server.set_responder(handler)

    client.tasks.attachments.upload(
        "task_8fk2", b"hello!", filename="receipt.pdf", content_type="application/pdf"
    )

    assert "authorization" not in calls[1].headers


def test_a_storage_rejection_surfaces_as_an_upload_failure_and_skips_confirmation(server, client):
    handler, calls = _upload_routes(storage_status=403)
    server.set_responder(handler)

    with pytest.raises(FivexerApiError) as excinfo:
        client.tasks.attachments.upload(
            "task_8fk2", b"hello!", filename="receipt.pdf", content_type="application/pdf"
        )

    assert excinfo.value.code == "upload_failed"
    assert excinfo.value.status_code == 403
    # The record stays `pending` rather than being confirmed against bytes that never landed.
    assert not any(c.url.path.endswith("/confirm") for c in calls)


def test_uploading_can_attribute_the_file_to_a_worker(server, client):
    handler, calls = _upload_routes()
    server.set_responder(handler)

    client.tasks.attachments.upload(
        "task_8fk2",
        b"hello!",
        filename="receipt.pdf",
        content_type="application/pdf",
        worker_id="agent_1",
    )

    assert read_body(calls[0])["workerId"] == "agent_1"
