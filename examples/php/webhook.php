<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Fivexer\SDK\Webhook\Webhook;

$rawBody = '{"event":"task.matched","data":{"taskId":"task_8fk2","workerId":"agent_1"}}';
$secret = 'whsec_test_secret';
$header = 't=1704067200000,v1=7f3b9c2e4d8a1f6e5b0c3d7a9e2f4b8c1d5e6a7f9b0c2d3e4f5a6b7c8d9e0f1a';

$event = Webhook::constructEvent($rawBody, $header, $secret);
echo "verified event: {$event->getEvent()}\n";
