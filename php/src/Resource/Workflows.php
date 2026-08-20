<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\StartRun;
use Fivexer\SDK\Model\WorkflowDefinition;
use Fivexer\SDK\Model\WorkflowDefinitionInput;
use Fivexer\SDK\Model\WorkflowDefinitionSummary;
use Fivexer\SDK\Model\WorkflowRun;
use Fivexer\SDK\Model\WorkflowRunPage;

/** Workflow definitions, and starting runs from them. */
final class Workflows
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    /** @return list<WorkflowDefinitionSummary> */
    public function list(): array
    {
        $data = $this->client->request('GET', '/workflows') ?? [];
        /** @var list<WorkflowDefinitionSummary> */
        return Json::parseEach($data, 'workflows', [WorkflowDefinitionSummary::class, 'fromArray']);
    }

    public function get(string $workflowId): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray(
            $this->client->request('GET', '/workflows/' . \rawurlencode($workflowId)) ?? []
        );
    }

    /**
     * Create or replace a definition. The engine validates the graph on save (unreachable or
     * dangling steps, a missing initial step, a machine step without a handler), so a bad
     * definition comes back as 400 invalid_workflow.
     */
    public function save(string $workflowId, WorkflowDefinitionInput $input): WorkflowDefinition
    {
        return WorkflowDefinition::fromArray(
            $this->client->request('PUT', '/workflows/' . \rawurlencode($workflowId), $input->toArray()) ?? []
        );
    }

    public function remove(string $workflowId): void
    {
        $this->client->request('DELETE', '/workflows/' . \rawurlencode($workflowId));
    }

    public function run(string $workflowId, ?StartRun $input = null): WorkflowRun
    {
        $body = ($input ?? new StartRun())->toArray();
        return WorkflowRun::fromArray(
            $this->client->request('POST', '/workflows/' . \rawurlencode($workflowId) . '/runs', $body) ?? []
        );
    }

    /**
     * Runs of one definition, newest-first and cursor-paginated. The workflow id is already in
     * the path, so it is deliberately not repeated as a filter.
     */
    public function listRuns(
        string $workflowId,
        ?string $status = null,
        ?string $cursor = null,
        ?int $limit = null,
    ): WorkflowRunPage {
        $data = $this->client->request('GET', '/workflows/' . \rawurlencode($workflowId) . '/runs', null, [
            'status' => $status,
            'cursor' => $cursor,
            'limit' => $limit,
        ]) ?? [];
        return WorkflowRunPage::fromArray($data);
    }
}
