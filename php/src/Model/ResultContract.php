<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * What a finished result must look like.
 *
 * `schema` (JSON Schema 2020-12; no `pattern`/`patternProperties`, local `$ref` only) is enforced
 * at completion: a result that misses it is refused with a 422 `result_invalid`, unless it reports
 * `success: false`. The keys `success`, `summary` and `timedOut` are set aside before the check.
 * `acceptanceCriteria` is shown to whoever does the work. Fixed at creation.
 *
 * Used both as input ({@see CreateTask::resultContract()}) and read back on
 * {@see TaskContext::$resultContract}.
 */
final class ResultContract
{
    public function __construct(
        /** @var array<string, mixed>|null The JSON Schema the result must satisfy */
        private ?array $schema = null,
        /** Plain-language definition of done, shown to whoever does the work */
        private ?string $acceptanceCriteria = null,
    ) {
    }

    /** @param array<string, mixed> $schema */
    public function schema(array $schema): self
    {
        $this->schema = $schema;
        return $this;
    }

    public function acceptanceCriteria(string $acceptanceCriteria): self
    {
        $this->acceptanceCriteria = $acceptanceCriteria;
        return $this;
    }

    /** @return array<string, mixed>|null */
    public function getSchema(): ?array
    {
        return $this->schema;
    }

    public function getAcceptanceCriteria(): ?string
    {
        return $this->acceptanceCriteria;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return Json::compact([
            'schema' => $this->schema,
            'acceptanceCriteria' => $this->acceptanceCriteria,
        ]);
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromArray(array $data): self
    {
        return new self(
            schema: $data['schema'] ?? null,
            acceptanceCriteria: isset($data['acceptanceCriteria']) ? (string) $data['acceptanceCriteria'] : null,
        );
    }

    /**
     * @param array<string, mixed> $data
     * @internal
     */
    public static function fromParent(array $data): ?self
    {
        return isset($data['resultContract']) && \is_array($data['resultContract'])
            ? self::fromArray($data['resultContract'])
            : null;
    }
}
