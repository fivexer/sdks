<?php

declare(strict_types=1);

namespace Fivexer\SDK\Model;

use Fivexer\SDK\Internal\Json;

/**
 * What may change about a task that already exists (`tasks()->update()`).
 *
 * Patch semantics: a field never set is left alone. The fields that can be cleared — title,
 * description, context, references and meta — each have a `clear…()` method that sends an
 * explicit null; a PHP null cannot say that on its own, because it already means "not set".
 * Setting a field after clearing it (or the reverse) keeps whichever call came last.
 *
 * `tags` and `priority` are routing: changing the tags changes who *should* have the work, and
 * if they no longer reach the worker holding it the task is taken back and requeued — read
 * {@see UpdateTaskResult::$requeued}. An edit that leaves the tags alone never moves anything.
 *
 * Fluent builder: set what you need, then hand it to the client.
 */
final class UpdateTaskInput
{
    public function __construct(
        /** @var list<string>|null */
        private ?array $tags = null,
        private ?float $priority = null,
        private ?string $title = null,
        private ?string $description = null,
        /** @var array<string, mixed>|null */
        private ?array $context = null,
        /** @var list<TaskReferenceInput>|null */
        private ?array $references = null,
        /** @var array<string, mixed>|null */
        private ?array $meta = null,
    ) {
    }

    /**
     * Fields to send as an explicit null, keyed by wire name.
     *
     * @var array<string, true>
     */
    private array $cleared = [];

    /**
     * Replace the routing tags. May requeue the task off its current holder.
     *
     * @param list<string> $tags
     */
    public function tags(array $tags): self
    {
        $this->tags = $tags;
        return $this;
    }

    public function priority(float $priority): self
    {
        $this->priority = $priority;
        return $this;
    }

    public function title(string $title): self
    {
        $this->title = $title;
        unset($this->cleared['title']);
        return $this;
    }

    /** Remove the title. Sends null. */
    public function clearTitle(): self
    {
        $this->title = null;
        $this->cleared['title'] = true;
        return $this;
    }

    public function description(string $description): self
    {
        $this->description = $description;
        unset($this->cleared['description']);
        return $this;
    }

    /** Remove the description. Sends null. */
    public function clearDescription(): self
    {
        $this->description = null;
        $this->cleared['description'] = true;
        return $this;
    }

    /** @param array<string, mixed> $context */
    public function context(array $context): self
    {
        $this->context = $context;
        unset($this->cleared['context']);
        return $this;
    }

    /** Remove the structured context. Sends null. */
    public function clearContext(): self
    {
        $this->context = null;
        $this->cleared['context'] = true;
        return $this;
    }

    /** @param list<TaskReferenceInput> $references */
    public function references(array $references): self
    {
        $this->references = $references;
        unset($this->cleared['references']);
        return $this;
    }

    /** Remove every reference. Sends null. */
    public function clearReferences(): self
    {
        $this->references = null;
        $this->cleared['references'] = true;
        return $this;
    }

    /** @param array<string, mixed> $meta */
    public function meta(array $meta): self
    {
        $this->meta = $meta;
        unset($this->cleared['meta']);
        return $this;
    }

    /** Remove the meta object. Sends null. */
    public function clearMeta(): self
    {
        $this->meta = null;
        $this->cleared['meta'] = true;
        return $this;
    }

    /**
     * Serialise for the wire: unset fields are omitted, cleared ones are sent as null.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $payload = Json::compact([
            'tags' => $this->tags,
            'priority' => $this->priority,
            'title' => $this->title,
            'description' => $this->description,
            'context' => $this->context,
            'references' => Json::each($this->references),
            'meta' => $this->meta,
        ]);
        foreach (\array_keys($this->cleared) as $field) {
            $payload[$field] = null;
        }
        return $payload;
    }
}
