<?php

namespace Chronicle;

use Chronicle\Exceptions\MissingActionException;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Exceptions\MissingSubjectException;
use Illuminate\Support\Str;

/**
 * Class EntryBuilder
 *
 * The EntryBuilder is responsible for constructing a Chronicle
 * entry payload before it enters the Chronicle processing pipeline.
 *
 * Responsibilities:
 *  - Collect audit entry information from the developer
 *  - Validate required fields (actor, action, subject)
 *  - Normalize data such as tags
 *  - Resolve actor and subject references
 *  - Generate the entry identifier
 *
 * The builder DOES NOT persist data. It only produces the
 * structured payload that will later be processed by Chronicle.
 *
 * Example usage:
 *
 * Chronicle::entry()
 *      ->actor($user)
 *      ->action('invoice.sent')
 *      ->subject($invoice)
 *      ->metadata(['email' => 'client@example.com'])
 *      ->tags(['billing'])
 *      ->build();
 */
class EntryBuilder
{
    /**
     * Actor responsible for the action.
     *
     * This may be:
     *  - an Eloquent model
     *  - a domain object
     *  - a string identifier
     */
    protected mixed $actor = null;

    /**
     * Action describing what occurred.
     *
     * Example:
     * "invoice.created"
     */
    protected ?string $action = null;

    /**
     * Subject affected by the action.
     */
    protected mixed $subject = null;

    /**
     * Optional domain-specific metadata.
     *
     * @var array<string, mixed>
     */
    protected array $metadata = [];

    /**
     * Optional execution context.
     *
     * Examples:
     *  - request id
     *  - IP address
     *  - CLI command
     *
     * @var array<string, mixed>
     */
    protected array $context = [];

    /**
     * Optional change diff information.
     *
     * @var array<string, mixed>
     */
    protected array $diff = [];

    /**
     * Optional tags used for grouping or filtering.
     *
     * @var array<string, mixed>
     */
    protected array $tags = [];

    /**
     * Correlation identifier used to group related entries.
     */
    protected ?string $correlationId = null;

    /**
     * Define the actor responsible for the action.
     */
    public function actor(mixed $actor): EntryBuilder
    {
        $this->actor = $actor;

        return $this;
    }

    /**
     * Define the action that occurred.
     */
    public function action(string $action): EntryBuilder
    {
        $this->action = $action;

        return $this;
    }

    /**
     * Define the subject affected by the action.
     */
    public function subject(mixed $subject): EntryBuilder
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Attach metadata to the entry.
     *
     * Metadata contains domain-specific data relevant
     * to the audit event.
     *
     * @param  array<string, mixed>  $metadata
     */
    public function metadata(array $metadata): EntryBuilder
    {
        $this->metadata = $metadata;

        return $this;
    }

    /**
     * Attach contextual execution data.
     *
     * Context is typically environment information
     * such as request IDs or runtime data.
     *
     * @param  array<string, mixed>  $context
     */
    public function context(array $context): EntryBuilder
    {
        $this->context = $context;

        return $this;
    }

    /**
     * Attach diff information describing changes.
     *
     * @param  array<string, mixed>  $diff
     */
    public function diff(array $diff): EntryBuilder
    {
        $this->diff = $diff;

        return $this;
    }

    /**
     * Assign tags to the entry.
     *
     * Tags are normalized before being stored.
     *
     * @param  array<string, mixed>  $tags
     */
    public function tags(array $tags): EntryBuilder
    {
        $this->tags = $tags;

        return $this;
    }

    /**
     * Assign a correlation identifier.
     *
     * Correlation IDs group multiple related entries
     * such as those belonging to a single request
     * or background job.
     */
    public function correlationId(string $correlationId): EntryBuilder
    {
        $this->correlationId = $correlationId;

        return $this;
    }

    /**
     * Build the entry payload.
     *
     * This method validates the builder state and returns
     * a fully structured entry payload ready for further
     * processing by Chronicle.
     *
     * @throws MissingActorException
     * @throws MissingActionException
     * @throws MissingSubjectException
     *
     * @return array<string, mixed>
     */
    public function build(): array
    {
        $this->validate();

        return [
            'id' => (string) Str::ulid(),
            'recorded_at' => now(),
            'actor_type' => $this->resolveType($this->actor),
            'actor_id' => $this->resolveId($this->actor),
            'action' => $this->action,
            'subject_type' => $this->resolveType($this->subject),
            'subject_id' => $this->resolveId($this->subject),
            'metadata' => $this->metadata ?: null,
            'context' => $this->context ?: null,
            'diff' => $this->diff ?: null,
            'tags' => $this->tags ? $this->normalizeTags($this->tags) : null,
            'correlation_id' => $this->correlationId,
        ];
    }

    /**
     * Validate required builder fields.
     *
     * @throws MissingActorException
     * @throws MissingActionException
     * @throws MissingSubjectException
     */
    protected function validate(): void
    {
        if (! $this->actor) {
            throw new MissingActorException;
        }

        if (! $this->action) {
            throw new MissingActionException;
        }

        if (! $this->subject) {
            throw new MissingSubjectException;
        }
    }

    /**
     * Resolve the entity type for actor or subject.
     *
     * Object are converted to their class name,
     * while scalar values are represented by their type.
     */
    protected function resolveType(mixed $entity): string
    {
        if (is_object($entity)) {
            return $entity::class;
        }

        return gettype($entity);
    }

    /**
     * Resolve the identifier for actor or subject.
     *
     * If the entity has an "id" property it will be used,
     * otherwise the entity is cast to string.
     */
    protected function resolveId(mixed $entity): string
    {
        if (is_object($entity) && isset($entity->id)) {
            return (string) $entity->id;
        }

        return (string) $entity;
    }

    /**
     * Normalize tags for consistent storage.
     *
     * Normalization includes:
     *  - trimming whitespace
     *  - converting to lowercase
     *  - removing duplicates
     *  - sorting alphabetically
     *
     * @param  array<string, mixed>  $tags
     * @return array<string, mixed>
     */
    protected function normalizeTags(array $tags): array
    {
        $tags = array_map(fn ($tag) => strtolower(trim($tag)), $tags);

        $tags = array_unique($tags);

        sort($tags);

        return array_values($tags);
    }
}
