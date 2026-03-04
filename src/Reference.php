<?php

namespace Chronicle;

/**
 * Class Reference
 *
 * Represents a normalized Chronicle entity reference.
 *
 * Every actor and subject is converted into a Reference
 * so the ledger stores deterministic identifiers.
 */
class Reference
{
    /**
     * Reference type (usually class name).
     */
    public string $type;

    /**
     * Identifier of the entity.
     */
    public string $id;

    /**
     * Create a new reference instance.
     */
    public function __construct(string $type, string $id)
    {
        $this->type = $type;
        $this->id = $id;
    }

    /**
     * Convert reference to array representation.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'id' => $this->id,
        ];
    }
}
