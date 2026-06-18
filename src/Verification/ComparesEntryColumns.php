<?php

declare(strict_types=1);

namespace Chronicle\Verification;

use Chronicle\Entry\Entry;
use Chronicle\Support\CanonicalPayloadSerializer;
use JsonException;

/**
 * Asserts that an entry's denormalized columns still agree with the verified
 * (hash-covered) payload. Without this, a tampered column - the value the UI,
 * reports, and queries actually read - would pass hash/chain verification.
 */
trait ComparesEntryColumns
{
    /**
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    protected function columnsMatchPayload(
        Entry $entry,
        array $payload,
        CanonicalPayloadSerializer $serializer,
    ): bool {
        $scalars = [
            'actor_type' => $entry->actor_type,
            'actor_id' => $entry->actor_id,
            'action' => $entry->action,
            'subject_type' => $entry->subject_type,
            'subject_id' => $entry->subject_id,
            'correlation_id' => $entry->correlation_id,
        ];

        foreach ($scalars as $key => $columnValue) {
            $payloadValue = $payload[$key] ?? null;

            if ($this->stringifyScalar($columnValue) !== $this->stringifyScalar($payloadValue)) {
                return false;
            }
        }

        $structured = [
            'metadata' => $entry->metadata ?? [],
            'context' => $entry->context ?? [],
            'tags' => $entry->tags ?? [],
            'diff' => $entry->diff,
        ];

        foreach ($structured as $key => $columnValue) {
            $columnCanonical = $serializer->serialize(['v' => $columnValue]);
            $payloadCanonical = $serializer->serialize(['v' => $payload[$key] ?? null]);

            if (! hash_equals($columnCanonical, $payloadCanonical)) {
                return false;
            }
        }

        return true;
    }

    /**
     * Normalize a scalar (or null) column/payload value to a string for a
     * type-lenient comparison. Mirrors the previous `(string)` coercion: null
     * and non-scalars become an empty string, scalars cast directly.
     */
    protected function stringifyScalar(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }
}
