<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;
use Fivexer\SDK\Model\UpsertWorker;

$client = new Fivexer(
    $_ENV['FIVEXER_BASE_URL'] ?? 'https://api.fivexer.com',
    $_ENV['FIVEXER_API_KEY'] ?? '',
);

$workerId = $client->workers()->upsert(
    (new UpsertWorker('agent_1'))->tags(['english', 'billing'])
);
echo "upserted worker: {$workerId}\n";

$task = $client->tasks()->create(
    (new CreateTask(['english', 'billing']))->priority(90)
);
echo "created task: {$task->getId()} ({$task->getStatus()})\n";

$queue = $client->workers()->queue($workerId);
echo 'queue size: ' . count($queue->getTaskIds()) . "\n";

$decisions = $client->decisions()->list(['taskId' => $task->getId(), 'limit' => 10]);
foreach ($decisions as $d) {
    echo "decision: worker={$d->getWorkerId()} status={$d->getStatus()}\n";
}
