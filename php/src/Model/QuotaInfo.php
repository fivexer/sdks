<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

/**
 * Parsed from the X-Quota-* headers of the most recent response that carried them.
 * Any field may be null when the corresponding header was absent.
 */
final class QuotaInfo
{
    public function __construct(
        public readonly ?int $taskRateLimit = null,
        public readonly ?int $taskRateRemaining = null,
        public readonly ?int $queuedTasksLimit = null,
        public readonly ?int $queuedTasksRemaining = null,
        public readonly ?int $workersLimit = null,
        public readonly ?int $workersRemaining = null,
        public readonly ?int $skillsLimit = null,
        public readonly ?int $skillsRemaining = null,
        public readonly ?int $workerSkillsLimit = null,
        public readonly ?int $workerSkillsRemaining = null,
    ) {
    }

    /**
     * Case-insensitive header lookup. Returns null if no quota headers present.
     *
     * @param array<string, list<string>|string> $headers Header name => value(s)
     */
    public static function fromHeaders(array $headers): ?self
    {
        $lower = [];
        foreach ($headers as $name => $value) {
            $lower[\strtolower((string) $name)] = \is_array($value) ? ($value[0] ?? null) : $value;
        }

        $mapping = [
            'taskRateLimit' => 'x-quota-task-rate-limit',
            'taskRateRemaining' => 'x-quota-task-rate-remaining',
            'queuedTasksLimit' => 'x-quota-queued-tasks-limit',
            'queuedTasksRemaining' => 'x-quota-queued-tasks-remaining',
            'workersLimit' => 'x-quota-workers-limit',
            'workersRemaining' => 'x-quota-workers-remaining',
            'skillsLimit' => 'x-quota-skills-limit',
            'skillsRemaining' => 'x-quota-skills-remaining',
            'workerSkillsLimit' => 'x-quota-worker-skills-limit',
            'workerSkillsRemaining' => 'x-quota-worker-skills-remaining',
        ];

        $found = [];
        foreach ($mapping as $field => $header) {
            // Malformed values are ignored (null), matching the Python and Java SDKs.
            if (isset($lower[$header]) && \is_numeric($lower[$header])) {
                $found[$field] = (int) $lower[$header];
            }
        }

        return $found === [] ? null : new self(...$found);
    }

    /**
     * True if at least one quota header was present.
     *
     * Written as a filter rather than a chain of `||` so that adding a quota kind does not add
     * a short-circuit branch that then needs its own test to cover.
     */
    public function hasAny(): bool
    {
        $set = \array_filter([
            $this->taskRateLimit,
            $this->taskRateRemaining,
            $this->queuedTasksLimit,
            $this->queuedTasksRemaining,
            $this->workersLimit,
            $this->workersRemaining,
            $this->skillsLimit,
            $this->skillsRemaining,
            $this->workerSkillsLimit,
            $this->workerSkillsRemaining,
        ], static fn (?int $value): bool => $value !== null);

        return $set !== [];
    }
}
