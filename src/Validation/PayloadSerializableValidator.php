<?php

namespace Chronicle\Validation;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\PrioritizedEntryExtension;
use Chronicle\Entry\PendingEntry;
use Chronicle\Exceptions\UnserializablePayloadException;
use Chronicle\Pipeline\ExtensionStage;
use Closure;

class PayloadSerializableValidator implements EntryExtension, PrioritizedEntryExtension
{
    public function stage(): ExtensionStage
    {
        return ExtensionStage::VALIDATE;
    }

    public function priority(): int
    {
        return -50;
    }

    public function process(PendingEntry $entry): PendingEntry
    {
        // User-supplied data lives in metadata, context, and diff.
        // $entry->payload() is always [] during the VALIDATE stage —
        // it is only populated by CanonicalizePayload, which runs after extensions.
        $metadata = $entry->attribute('metadata');
        $context = $entry->attribute('context');
        $diff = $entry->attribute('diff');

        if (is_array($metadata)) {
            $this->walk($metadata);
        }

        if (is_array($context)) {
            $this->walk($context);
        }

        if (is_array($diff)) {
            $this->walk($diff);
        }

        // Catch-all for exotic non-serializable scalars (INF, NAN) that pass
        // the recursive type checks but are rejected by json_encode.
        $combined = [
            'metadata' => $metadata,
            'context' => $context,
            'diff' => $diff,
        ];

        $encoded = json_encode($combined);

        if ($encoded === false) {
            throw UnserializablePayloadException::notJsonSerializable(json_last_error_msg());
        }

        return $entry;
    }

    /**
     * @param  array<mixed>  $data
     */
    private function walk(array $data): void
    {
        foreach ($data as $value) {
            if ($value instanceof Closure) {
                throw UnserializablePayloadException::containsClosure();
            }

            if (is_resource($value)) {
                throw UnserializablePayloadException::containsResource();
            }

            if (is_object($value)) {
                throw UnserializablePayloadException::containsObject($value::class);
            }

            if (is_array($value)) {
                $this->walk($value);
            }
        }
    }
}
