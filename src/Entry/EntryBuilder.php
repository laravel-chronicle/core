<?php

namespace Chronicle\Entry;

use Chronicle\ChronicleManager;
use Chronicle\Contracts\ReferenceResolver;
use Chronicle\Exceptions\MissingActionException;
use Chronicle\Exceptions\MissingActorException;
use Chronicle\Exceptions\MissingSubjectException;
use Illuminate\Support\Carbon;
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
     * Resolver used to convert actor and subject values
     * into Chronicle references.
     */
    protected ReferenceResolver $resolver;

    protected ChronicleManager $manager;

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
     * @var string[]
     */
    protected array $tags = [];

    /**
     * Correlation identifier used to group related entries.
     */
    protected ?string $correlationId = null;

    /**
     * Create a new EntryBuilder instance.
     */
    public function __construct(
        ReferenceResolver $resolver,
        ChronicleManager $manager,
    ) {
        $this->resolver = $resolver;
        $this->manager = $manager;
    }

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
     * Attach tags to the Chronicle entry.
     *
     * Tags are normalized to ensure deterministic payload
     * serialization:
     *
     * - converted to lowercase
     * - trimmed
     * - duplicates removed
     * - sorted alphabetically
     *
     * @param  string[]  $tags
     */
    public function tags(array $tags): EntryBuilder
    {
        $this->tags = collect($tags)
            ->map(function (string $tag) {
                return strtolower(trim($tag));
            })
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();

        return $this;
    }

    /**
     * Assign a correlation identifier.
     *
     * Correlation IDs group multiple related entries
     * such as those belonging to a single request
     * or background job.
     */
    public function correlation(string $correlationId): EntryBuilder
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
     * @return array<string, mixed>
     *
     * @throws MissingActorException
     * @throws MissingActionException
     * @throws MissingSubjectException
     */
    public function build(): array
    {
        $this->validate();

        $actor = $this->resolver->resolve($this->actor);
        $subject = $this->resolver->resolve($this->subject);

        if (! $this->correlationId) {
            $this->correlationId = $this->manager->currentCorrelation();
        }

        return [
            'id' => (string) Str::ulid(),
            'actor_type' => $actor->type,
            'actor_id' => $actor->id,
            'action' => $this->action,
            'subject_type' => $subject->type,
            'subject_id' => $subject->id,
            'metadata' => $this->metadata ?: [],
            'context' => $this->context ?: [],
            //            'diff' => $this->diff ?: null,
            'tags' => $this->tags,
            'correlation_id' => $this->correlationId,
            'created_at' => Carbon::now('UTC'),
        ];
    }

    /**
     * Build and persist the Chronicle entry.
     */
    public function commit(): void
    {
        $payload = $this->build();

        $this->manager->commit($payload);
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
        if ($this->actor === null) {
            throw new MissingActorException;
        }

        if ($this->action === null || trim($this->action) === '') {
            throw new MissingActionException;
        }

        if ($this->subject === null) {
            throw new MissingSubjectException;
        }
    }
}
