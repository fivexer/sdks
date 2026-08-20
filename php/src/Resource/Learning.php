<?php

declare(strict_types=1);

namespace Fivexer\SDK\Resource;

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Internal\Json;
use Fivexer\SDK\Model\LearnedWeightsPreview;
use Fivexer\SDK\Model\LearningFeedbackItem;
use Fivexer\SDK\Model\LearningFeedbackResult;
use Fivexer\SDK\Model\LearningStatus;
use Fivexer\SDK\Model\RevertedWeights;
use Fivexer\SDK\Model\WorkerLearningStats;

/**
 * The reinforcement-learning layer: feedback in, re-ranked routing weights out.
 *
 * Learning only re-ranks candidates that are already eligible — hard rules (vetoes, thresholds,
 * CIDR, backlog limits) always apply first and stay deterministic.
 */
final class Learning
{
    public function __construct(private readonly Fivexer $client)
    {
    }

    public function status(): LearningStatus
    {
        return LearningStatus::fromArray($this->client->request('GET', '/learning/status') ?? []);
    }

    public function workerStats(string $workerId): WorkerLearningStats
    {
        return WorkerLearningStats::fromArray(
            $this->client->request('GET', '/learning/workers/' . \rawurlencode($workerId)) ?? []
        );
    }

    /** What applying the learned weights would change, without writing anything. */
    public function previewWeights(?string $workerId = null): LearnedWeightsPreview
    {
        return LearnedWeightsPreview::fromArray(
            $this->client->request('GET', '/learning/weights/preview', null, ['workerId' => $workerId]) ?? []
        );
    }

    /**
     * @param list<string>|null $workerIds The workers to update, or null for every worker
     * @return array<string, array<string, float>>
     */
    public function applyWeights(?array $workerIds = null): array
    {
        $body = $workerIds === null ? [] : ['workerIds' => $workerIds];
        $data = $this->client->request('POST', '/learning/weights/apply', $body) ?? [];
        /** @var array<string, array<string, float>> */
        return $data['applied'] ?? [];
    }

    /**
     * Undo the last applyWeights(), restoring each worker's saved snapshot. Workers who were
     * never synced have nothing to restore and are absent from the result.
     *
     * @param list<string>|null $workerIds The workers to revert, or null for every worker
     */
    public function revertWeights(?array $workerIds = null): RevertedWeights
    {
        $body = $workerIds === null ? [] : ['workerIds' => $workerIds];
        return RevertedWeights::fromArray(
            $this->client->request('POST', '/learning/weights/revert', $body) ?? []
        );
    }

    /**
     * Report outcome signals against a matched task.
     *
     * @param array<string, float> $signals
     */
    public function feedback(string $taskId, array $signals): bool
    {
        $data = $this->client->request(
            'POST',
            '/tasks/' . \rawurlencode($taskId) . '/feedback',
            ['signals' => $signals]
        ) ?? [];
        return (bool) ($data['ok'] ?? false);
    }

    /** Report a direct reward against a matched task. */
    public function reward(string $taskId, float $reward): bool
    {
        $data = $this->client->request(
            'POST',
            '/tasks/' . \rawurlencode($taskId) . '/reward',
            ['reward' => $reward]
        ) ?? [];
        return (bool) ($data['ok'] ?? false);
    }

    /**
     * Bulk feedback. Partial success: each result carries its own ok flag and error.
     *
     * @param list<LearningFeedbackItem> $items
     * @return list<LearningFeedbackResult>
     */
    public function feedbackBulk(array $items): array
    {
        $data = $this->client->request('POST', '/learning/feedback', [
            'items' => Json::each($items),
        ]) ?? [];
        /** @var list<LearningFeedbackResult> */
        return Json::parseEach($data, 'results', [LearningFeedbackResult::class, 'fromArray']);
    }

    /** Discard the learned model and start over. */
    public function reset(): bool
    {
        $data = $this->client->request('POST', '/learning/reset', []) ?? [];
        return (bool) ($data['ok'] ?? false);
    }
}
