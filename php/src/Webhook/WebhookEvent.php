<?php

declare(strict_types=1);

namespace Fivexer\SDK\Webhook;

use Fivexer\SDK\Exception\FivexerException;

/**
 * A verified webhook payload. event is the event type (e.g. 'task.matched')
 * when the payload carried one; data is the full parsed JSON array.
 */
final class WebhookEvent
{
    /**
     * @param array<string, mixed>|null $data
     */
    public function __construct(
        public readonly ?string $event,
        public readonly ?array $data,
    ) {
    }

    /**
     * @throws FivexerException when the payload is not valid JSON
     */
    public static function fromPayload(string $payload): self
    {
        $decoded = \json_decode($payload, true);
        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new FivexerException('webhook payload is not valid JSON');
        }
        // Valid JSON but not an object (a JSON array or scalar): no event, no data —
        // matches the Java SDK's handling of unexpected webhook shapes.
        if (!\is_array($decoded) || (\array_is_list($decoded) && $decoded !== [])) {
            return new self(null, null);
        }
        $event = isset($decoded['event']) && \is_string($decoded['event']) ? $decoded['event'] : null;
        return new self($event, $decoded);
    }
}
