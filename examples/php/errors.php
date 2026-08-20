<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Fivexer\SDK\Exception\FivexerApiException;
use Fivexer\SDK\Fivexer;
use Fivexer\SDK\Model\CreateTask;

$client = new Fivexer(
    $_ENV['FIVEXER_BASE_URL'] ?? 'https://api.fivexer.com',
    $_ENV['FIVEXER_API_KEY'] ?? '',
);

try {
    $client->tasks()->create(new CreateTask(['english']));
} catch (FivexerApiException $e) {
    echo "API error: {$e->getStatusCode()} {$e->getCode()}\n";
    if ($e->getRetryAfterSeconds() !== null) {
        echo "retry after: {$e->getRetryAfterSeconds()}\n";
    }
    if ($e->getQuota() !== null) {
        echo "task rate remaining: {$e->getQuota()->getTaskRateRemaining()}\n";
    }
}
