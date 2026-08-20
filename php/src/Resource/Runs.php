<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\WorkflowRun;
use Fivexer\SDK\Model\WorkflowRunPage;
use Fivexer\SDK\Model\WorkflowRunSteps;

/** Workflow runs across the workspace, including the external (callback) step outcomes. */
final class Runs
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    public function list(
        ?string $status = null,
        ?string $workflowId = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): WorkflowRunPage {
        $data = $this->client->request('GET', '/workflow-runs', null, [
            'status' => $status,
            'workflowId' => $workflowId,
            'cursor' => $cursor,
            'limit' => $limit,
        ]) ?? [];
        return WorkflowRunPage::fromArray($data);
    }

    public function get(string $runId): WorkflowRun
    {
        return WorkflowRun::fromArray(
            $this->client->request('GET', '/workflow-runs/' . \rawurlencode($runId)) ?? []
        );
    }

    /** The per-step state view — what a canvas UI paints. */
    public function steps(string $runId): WorkflowRunSteps
    {
        return WorkflowRunSteps::fromArray(
            $this->client->request('GET', '/workflow-runs/' . \rawurlencode($runId) . '/steps') ?? []
        );
    }

    public function cancel(string $runId): WorkflowRun
    {
        return WorkflowRun::fromArray(
            $this->client->request('POST', '/workflow-runs/' . \rawurlencode($runId) . '/cancel', []) ?? []
        );
    }

    /**
     * Complete an external (callback) step, advancing the run.
     *
     * @param array<string, mixed>|null $data
     */
    public function completeStep(string $runId, string $stepId, ?array $data = null): WorkflowRun
    {
        $body = $data === null ? [] : ['data' => $data];
        $path = '/workflow-runs/' . \rawurlencode($runId) . '/steps/' . \rawurlencode($stepId) . '/complete';
        return WorkflowRun::fromArray($this->client->request('POST', $path, $body) ?? []);
    }

    /** Fail an external (callback) step. */
    public function failStep(string $runId, string $stepId, ?string $error = null): WorkflowRun
    {
        $body = $error === null ? [] : ['error' => $error];
        $path = '/workflow-runs/' . \rawurlencode($runId) . '/steps/' . \rawurlencode($stepId) . '/fail';
        return WorkflowRun::fromArray($this->client->request('POST', $path, $body) ?? []);
    }
}
