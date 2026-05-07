<?php

namespace Chronicle\Entry;

/**
 * Represents an audit entry currently being processed
 * through the Chronicle pipeline.
 *
 * A PendingEntry is mutable while inside the pipeline.
 * Once persisted, it becomes an immutable Entry record.
 */
class PendingEntry
{
    /**
     * Base entry attributes produces by EntryBuilder.
     *
     * @var array<string, mixed>
     */
    protected array $attributes;

    /**
     * Canonical payload representation.
     *
     * @var array<string, mixed>
     */
    protected array $payload = [];

    /**
     * SHA-256 hash of the canonical payload.
     */
    protected ?string $payloadHash = null;

    /**
     * Chain hash linking this entry with the previous one.
     */
    protected ?string $chainHash = null;

    /**
     * Checkpoint identifier.
     */
    protected ?string $checkpointId = null;

    /**
     * Create a new pending entry.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(array $attributes)
    {
        $this->attributes = $attributes;
    }

    /**
     * Get entry attributes.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return $this->attributes;
    }

    /**
     * Get a single attribute.
     */
    public function attribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    /**
     * Set a single attribute.
     */
    public function setAttribute(string $key, mixed $value): void
    {
        $this->attributes[$key] = $value;
    }

    /**
     * Set a canonical payload.
     *
     * @param  array<string, mixed>  $payload
     */
    public function setPayload(array $payload): void
    {
        $this->payload = $payload;
    }

    /**
     * Get canonical payload.
     *
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->payload;
    }

    /**
     * Set the payload hash.
     */
    public function setPayloadHash(string $hash): void
    {
        $this->payloadHash = $hash;
    }

    /**
     * Get payload hash.
     */
    public function payloadHash(): ?string
    {
        return $this->payloadHash;
    }

    /**
     * Set chain hash.
     */
    public function setChainHash(string $hash): void
    {
        $this->chainHash = $hash;
    }

    /**
     * Get chain hash.
     */
    public function chainHash(): ?string
    {
        return $this->chainHash;
    }

    /**
     * Set checkpoint id.
     */
    public function setCheckpointId(string $checkpointId): void
    {
        $this->checkpointId = $checkpointId;
    }

    /**
     * Convert a pending entry into a database payload.
     *
     * @return array<string, mixed>
     */
    public function toDatabasePayload(): array
    {
        return array_merge($this->attributes, [
            'payload' => $this->payload,
            'payload_hash' => $this->payloadHash,
            'chain_hash' => $this->chainHash,
            'checkpoint_id' => $this->checkpointId,
        ]);
    }
}
