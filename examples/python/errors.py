"""Demonstrate resilient error handling with quota and retry-after."""

import os

from fivexer import CreateTask, Fivexer, FivexerApiError


def main() -> None:
    client = Fivexer(
        base_url=os.environ["FIVEXER_BASE_URL"],
        api_key=os.environ["FIVEXER_API_KEY"],
    )

    try:
        client.tasks.create(CreateTask(tags=["english"]))
    except FivexerApiError as e:
        print(f"API error: {e.status_code} {e.code}")
        if e.retry_after:
            print(f"retry after: {e.retry_after} seconds")
        if e.quota:
            print(f"task rate remaining: {e.quota.task_rate_remaining}")


if __name__ == "__main__":
    main()
