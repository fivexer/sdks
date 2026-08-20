"""Fivexer Python SDK quickstart.

Creates a worker, enqueues a task, and inspects the match decision.
Set FIVEXER_BASE_URL and FIVEXER_API_KEY before running.
"""

import os

from fivexer import CreateTask, Fivexer, ListDecisionsQuery, UpsertWorker


def main() -> None:
    client = Fivexer(
        base_url=os.environ["FIVEXER_BASE_URL"],
        api_key=os.environ["FIVEXER_API_KEY"],
    )

    # Register a worker with skills/tags.
    worker_id = client.workers.upsert(
        UpsertWorker(id="agent_1", tags=["english", "billing"])
    )
    print(f"upserted worker: {worker_id}")

    # Create a task; the platform queues it and continuously tries to match.
    task = client.tasks.create(
        CreateTask(tags=["english", "billing"], priority=90)
    )
    print(f"created task: {task.id} ({task.status})")

    # Inspect the worker's current tentative queue.
    queue = client.workers.queue(worker_id)
    print(f"queue length: {len(queue.task_ids)}")

    # See who matched, and why.
    decisions = client.decisions.list(
        ListDecisionsQuery(task_id=task.id, limit=10)
    )
    for decision in decisions:
        print(f"decision: worker={decision.worker_id} status={decision.status}")


if __name__ == "__main__":
    main()
