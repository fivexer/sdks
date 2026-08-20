<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * One node of a workflow graph, used both when saving a definition and when reading one back.
 *
 * `defaultNextStepId` is genuinely tri-state, which is why it gets special handling rather than
 * riding on the usual omit-nulls rule: *absent* means no fallback was declared, while an
 * explicit `null` ends the workflow after this step. Call {@see self::endWorkflow()} to express
 * the latter — a plain null property cannot be told apart from "never set".
 */
final class WorkflowStep
{
    private bool $terminal = false;

    /**
     * @param array<string, mixed>|null $assignmentTemplate
     * @param string|array<string, mixed>|null $targetUser 'initiator'|'previous'|a worker id|['tag' => '...']
     * @param array<string, mixed>|null $machineTask
     * @param array<string, mixed>|null $external
     * @param list<WorkflowRouting>|null $routing
     * @param list<string>|null $parallelStepIds
     */
    public function __construct(
        private readonly string $id,
        private readonly string $name,
        private ?string $taskType = null,
        private ?array $assignmentTemplate = null,
        private string|array|null $targetUser = null,
        private ?array $machineTask = null,
        private ?array $external = null,
        private ?array $routing = null,
        private ?string $defaultNextStepId = null,
        private ?array $parallelStepIds = null,
        private ?bool $waitForAll = null,
        private ?string $failurePolicy = null,
        private ?int $maxRetries = null,
        private ?int $timeoutMs = null,
    ) {
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getTaskType(): ?string
    {
        return $this->taskType;
    }

    /** @return array<string, mixed>|null */
    public function getAssignmentTemplate(): ?array
    {
        return $this->assignmentTemplate;
    }

    /** @return string|array<string, mixed>|null */
    public function getTargetUser(): string|array|null
    {
        return $this->targetUser;
    }

    /** @return array<string, mixed>|null */
    public function getMachineTask(): ?array
    {
        return $this->machineTask;
    }

    /** @return array<string, mixed>|null */
    public function getExternal(): ?array
    {
        return $this->external;
    }

    /** @return list<WorkflowRouting>|null */
    public function getRouting(): ?array
    {
        return $this->routing;
    }

    public function getDefaultNextStepId(): ?string
    {
        return $this->defaultNextStepId;
    }

    /** @return list<string>|null */
    public function getParallelStepIds(): ?array
    {
        return $this->parallelStepIds;
    }

    public function getWaitForAll(): ?bool
    {
        return $this->waitForAll;
    }

    public function getFailurePolicy(): ?string
    {
        return $this->failurePolicy;
    }

    public function getMaxRetries(): ?int
    {
        return $this->maxRetries;
    }

    public function getTimeoutMs(): ?int
    {
        return $this->timeoutMs;
    }

    /** True when this step ends the workflow (an explicit null successor). */
    public function isTerminal(): bool
    {
        return $this->terminal;
    }

    public function taskType(string $taskType): self
    {
        $this->taskType = $taskType;
        return $this;
    }

    /** @param array<string, mixed> $assignmentTemplate */
    public function assignmentTemplate(array $assignmentTemplate): self
    {
        $this->assignmentTemplate = $assignmentTemplate;
        return $this;
    }

    /** @param string|array<string, mixed> $targetUser */
    public function targetUser(string|array $targetUser): self
    {
        $this->targetUser = $targetUser;
        return $this;
    }

    /** @param array<string, mixed> $machineTask */
    public function machineTask(array $machineTask): self
    {
        $this->machineTask = $machineTask;
        return $this;
    }

    /** @param array<string, mixed> $external */
    public function external(array $external): self
    {
        $this->external = $external;
        return $this;
    }

    /** @param list<WorkflowRouting> $routing */
    public function routing(array $routing): self
    {
        $this->routing = $routing;
        return $this;
    }

    public function defaultNextStepId(string $defaultNextStepId): self
    {
        $this->defaultNextStepId = $defaultNextStepId;
        $this->terminal = false;
        return $this;
    }

    /** @param list<string> $parallelStepIds */
    public function parallelStepIds(array $parallelStepIds): self
    {
        $this->parallelStepIds = $parallelStepIds;
        return $this;
    }

    public function waitForAll(bool $waitForAll): self
    {
        $this->waitForAll = $waitForAll;
        return $this;
    }

    public function failurePolicy(string $failurePolicy): self
    {
        $this->failurePolicy = $failurePolicy;
        return $this;
    }

    public function maxRetries(int $maxRetries): self
    {
        $this->maxRetries = $maxRetries;
        return $this;
    }

    public function timeoutMs(int $timeoutMs): self
    {
        $this->timeoutMs = $timeoutMs;
        return $this;
    }

    /** Mark this step as ending the workflow, serialising `defaultNextStepId: null`. */
    public function endWorkflow(): self
    {
        $this->defaultNextStepId = null;
        $this->terminal = true;
        return $this;
    }

    /**
     * Serialise, omitting unset fields but writing the explicit null successor that terminates
     * a workflow.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = Json::compact([
            'taskType' => $this->taskType,
            'assignmentTemplate' => $this->assignmentTemplate,
            'targetUser' => $this->targetUser,
            'machineTask' => $this->machineTask,
            'external' => $this->external,
            'routing' => Json::each($this->routing),
            'defaultNextStepId' => $this->defaultNextStepId,
            'parallelStepIds' => $this->parallelStepIds,
            'waitForAll' => $this->waitForAll,
            'failurePolicy' => $this->failurePolicy,
            'maxRetries' => $this->maxRetries,
            'timeoutMs' => $this->timeoutMs,
        ]);
        $payload = ['id' => $this->id, 'name' => $this->name] + $payload;
        if ($this->terminal) {
            $payload['defaultNextStepId'] = null;
        }
        return $payload;
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        $step = new self(
            id: (string) ($data['id'] ?? ''),
            name: (string) ($data['name'] ?? ''),
            taskType: isset($data['taskType']) ? (string) $data['taskType'] : null,
            assignmentTemplate: $data['assignmentTemplate'] ?? null,
            targetUser: $data['targetUser'] ?? null,
            machineTask: $data['machineTask'] ?? null,
            external: $data['external'] ?? null,
            routing: Json::parseEachOrNull($data, 'routing', [WorkflowRouting::class, 'fromArray']),
            defaultNextStepId: isset($data['defaultNextStepId']) ? (string) $data['defaultNextStepId'] : null,
            parallelStepIds: isset($data['parallelStepIds'])
                ? \array_map('strval', $data['parallelStepIds'])
                : null,
            waitForAll: isset($data['waitForAll']) ? (bool) $data['waitForAll'] : null,
            failurePolicy: isset($data['failurePolicy']) ? (string) $data['failurePolicy'] : null,
            maxRetries: isset($data['maxRetries']) ? (int) $data['maxRetries'] : null,
            timeoutMs: isset($data['timeoutMs']) ? (int) $data['timeoutMs'] : null,
        );
        // array_key_exists, not isset: an explicit null is exactly what marks a terminal step.
        if (\array_key_exists('defaultNextStepId', $data) && $data['defaultNextStepId'] === null) {
            $step->endWorkflow();
        }
        return $step;
    }
}
