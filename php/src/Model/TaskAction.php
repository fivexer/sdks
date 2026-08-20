<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/** Response of /tasks/{id}/accept|reject|complete: the task id and its new status. */
final class TaskAction
{
    public function __construct(
        public readonly string $id,
        public readonly string $status,
    ) {
    }

    /** @param array<string, mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (string) $data['id'],
            status: (string) $data['status'],
        );
    }
}
