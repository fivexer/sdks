"""Worker-plane attachments and voice ICE.

Attachments are how an unattended agent hands over a deliverable as a file rather than a
chunked comment thread. The storage core is the workspace plane's — presigned PUT, then a
confirm that makes the bytes readable — with two deliberate differences: the uploader comes
from the session, so there is no `worker_id` input to spoof, and there is no `remove`, because
a worker who could delete files could erase the evidence of their own work.

Voice is **experimental** and not production-ready; the tests below pin only the path and the
`voice_disabled` behaviour so that surface cannot drift silently while it settles.
"""

from __future__ import annotations

import asyncio

import httpx
import pytest

from fivexer import FivexerApiError, WorkerCreateAttachment
from tests.conftest import json_response, read_body

ATTACHMENT = {
    "id": "att_1",
    "taskId": "task_1",
    "filename": "report.pdf",
    "contentType": "application/pdf",
    "sizeBytes": 11,
    "status": "pending",
    "uploader": {"type": "worker", "id": "agent_1"},
    "createdAt": 1_756_000_000_000,
}
UPLOAD = {
    "url": "https://storage.test/bucket/att_1?sig=abc",
    "method": "PUT",
    "headers": {"content-type": "application/pdf", "x-amz-meta-task": "task_1"},
    "expiresAt": 1_756_000_300_000,
}


def test_create_posts_to_the_portal_path_with_the_session_token(server, worker):
    server.set_response(json_response(200, {"attachment": ATTACHMENT, "upload": UPLOAD}))

    created = worker.attachments.create(
        "task_1", WorkerCreateAttachment(filename="report.pdf", content_type="application/pdf", size_bytes=11)
    )

    assert server.last.method == "POST"
    assert server.last.url.path == "/v1/portal/tasks/task_1/attachments"
    assert server.last.headers["authorization"] == "Bearer wt_s3ss10n"
    assert created.attachment.id == "att_1"
    assert created.upload.url == UPLOAD["url"]


def test_the_create_body_carries_no_worker_id(server, worker):
    server.set_response(json_response(200, {"attachment": ATTACHMENT, "upload": UPLOAD}))

    worker.attachments.create(
        "task_1", WorkerCreateAttachment(filename="report.pdf", content_type="application/pdf", size_bytes=11)
    )

    # On this plane the uploader is the session. A workerId field here would be an invitation to
    # attribute a file to someone else, which is exactly what the separate input type prevents.
    assert read_body(server.last) == {
        "filename": "report.pdf",
        "contentType": "application/pdf",
        "sizeBytes": 11,
    }


def test_confirm_list_and_download_use_the_portal_paths(server, worker):
    server.set_response(json_response(200, {"attachment": {**ATTACHMENT, "status": "ready"}}))
    confirmed = worker.attachments.confirm("task_1", "att_1")
    assert server.last.url.path == "/v1/portal/tasks/task_1/attachments/att_1/confirm"
    assert confirmed.status == "ready"

    server.set_response(json_response(200, {"attachments": [ATTACHMENT]}))
    listed = worker.attachments.list("task_1")
    assert server.last.method == "GET"
    assert server.last.url.path == "/v1/portal/tasks/task_1/attachments"
    assert [a.id for a in listed] == ["att_1"]

    server.set_response(json_response(200, {"url": "https://storage.test/get", "expiresAt": 1}))
    download = worker.attachments.download("task_1", "att_1")
    assert server.last.url.path == "/v1/portal/tasks/task_1/attachments/att_1/download"
    assert download.url == "https://storage.test/get"


def test_ids_with_slashes_cannot_escape_the_portal_path(server, worker):
    server.set_response(json_response(200, {"attachments": []}))

    worker.attachments.list("tenant/task")

    assert server.last.url.raw_path.decode() == "/v1/portal/tasks/tenant%2Ftask/attachments"


def test_upload_creates_puts_the_bytes_then_confirms(server, worker):
    calls: list[httpx.Request] = []

    def responder(request: httpx.Request) -> httpx.Response:
        calls.append(request)
        if request.url.host == "storage.test":
            return httpx.Response(200)
        if request.url.path.endswith("/confirm"):
            return json_response(200, {"attachment": {**ATTACHMENT, "status": "ready"}})
        return json_response(200, {"attachment": ATTACHMENT, "upload": UPLOAD})

    server.set_responder(responder)

    attachment = worker.attachments.upload("task_1", b"hello world", "report.pdf", "application/pdf")

    assert attachment.status == "ready"
    assert [c.method for c in calls] == ["POST", "PUT", "POST"]
    # The size is derived from the payload, never trusted from the caller — the server HEADs the
    # object on confirm, so a wrong number here fails late instead of at the create.
    assert read_body(calls[0])["sizeBytes"] == 11
    assert calls[1].url.host == "storage.test"
    # The presigned headers are part of the signature: re-ordering or dropping one invalidates it.
    assert calls[1].headers["x-amz-meta-task"] == "task_1"
    # The session token must not reach a third-party storage host.
    assert "authorization" not in calls[1].headers


def test_a_failed_storage_put_surfaces_as_upload_failed(server, worker):
    def responder(request: httpx.Request) -> httpx.Response:
        if request.url.host == "storage.test":
            return httpx.Response(403)
        return json_response(200, {"attachment": ATTACHMENT, "upload": UPLOAD})

    server.set_responder(responder)

    with pytest.raises(FivexerApiError) as raised:
        worker.attachments.upload("task_1", b"hello world", "report.pdf", "application/pdf")

    # A storage rejection is not a /v1 error; reporting it as one code keeps it diagnosable.
    assert raised.value.code == "upload_failed"


def test_the_worker_plane_has_no_attachment_remove(worker):
    # Files on a task are an operator's to manage and a worker's only to add and read. The
    # absence is the contract, so it is asserted rather than left to be noticed.
    assert not hasattr(worker.attachments, "remove")


def test_the_async_worker_mirrors_the_attachment_surface(server, async_worker):
    async def scenario() -> None:
        server.set_response(json_response(200, {"attachments": [ATTACHMENT]}))
        listed = await async_worker.attachments.list("task_1")
        assert [a.id for a in listed] == ["att_1"]

        def responder(request: httpx.Request) -> httpx.Response:
            if request.url.host == "storage.test":
                return httpx.Response(200)
            if request.url.path.endswith("/confirm"):
                return json_response(200, {"attachment": {**ATTACHMENT, "status": "ready"}})
            return json_response(200, {"attachment": ATTACHMENT, "upload": UPLOAD})

        server.set_responder(responder)
        attachment = await async_worker.attachments.upload("task_1", b"hello world", "report.pdf", "application/pdf")
        assert attachment.status == "ready"

        server.set_response(json_response(200, {"url": "https://storage.test/get", "expiresAt": 1}))
        download = await async_worker.attachments.download("task_1", "att_1")
        assert server.last.url.path == "/v1/portal/tasks/task_1/attachments/att_1/download"
        assert download.url == "https://storage.test/get"

    asyncio.run(scenario())


def test_the_async_upload_reports_a_storage_rejection_the_same_way(server, async_worker):
    async def scenario() -> None:
        def responder(request: httpx.Request) -> httpx.Response:
            if request.url.host == "storage.test":
                return httpx.Response(500)
            return json_response(200, {"attachment": ATTACHMENT, "upload": UPLOAD})

        server.set_responder(responder)
        with pytest.raises(FivexerApiError) as raised:
            await async_worker.attachments.upload("task_1", b"hello world", "r.pdf", "application/pdf")
        assert raised.value.code == "upload_failed"

    asyncio.run(scenario())


ICE = {
    "iceServers": [
        {"urls": "stun:stun.test:3478"},
        {"urls": ["turn:turn.test:3478", "turns:turn.test:5349"], "username": "agent_1", "credential": "s3cret"},
    ]
}


def test_worker_voice_ice_reads_the_portal_path(server, worker):
    server.set_response(json_response(200, ICE))

    ice = worker.voice_ice()

    assert server.last.method == "GET"
    assert server.last.url.path == "/v1/portal/voice/ice"
    assert len(ice.ice_servers) == 2
    assert ice.ice_servers[0].urls == "stun:stun.test:3478"
    assert ice.ice_servers[1].urls == ["turn:turn.test:3478", "turns:turn.test:5349"]


def test_the_session_plane_receives_the_turn_credential(server, worker):
    server.set_response(json_response(200, ICE))

    ice = worker.voice_ice()

    # The console's read view reports `credentialSet` and withholds the value; a session plane
    # must hand over the real secret or the browser cannot authenticate to the relay.
    assert ice.ice_servers[1].credential == "s3cret"
    assert ice.ice_servers[1].username == "agent_1"


def test_voice_disabled_raises_rather_than_reading_as_no_relays(server, worker):
    server.set_response(json_response(404, {"error": {"code": "voice_disabled", "message": "voice is off"}}))

    with pytest.raises(FivexerApiError) as raised:
        worker.voice_ice()

    # An empty list would read as "no relay configured, go direct" — a different, and silently
    # broken, outcome from "this workspace has no voice".
    assert raised.value.status_code == 404
    assert raised.value.code == "voice_disabled"


def test_supervisor_voice_ice_reads_its_own_path(server, supervisor):
    server.set_response(json_response(200, ICE))

    ice = supervisor.voice_ice()

    assert server.last.url.path == "/v1/supervisor/voice/ice"
    assert server.last.headers["authorization"] == "Bearer sv_s3ss10n"
    assert len(ice.ice_servers) == 2


def test_the_async_planes_mirror_voice_ice(server, async_worker, async_supervisor):
    async def scenario() -> None:
        server.set_response(json_response(200, ICE))
        assert len((await async_worker.voice_ice()).ice_servers) == 2
        assert server.last.url.path == "/v1/portal/voice/ice"

        assert len((await async_supervisor.voice_ice()).ice_servers) == 2
        assert server.last.url.path == "/v1/supervisor/voice/ice"

    asyncio.run(scenario())
