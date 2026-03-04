<?php

namespace Chronicle\Serialization;

use DateTimeInterface;
use JsonException;

/**
 * Class CanonicalPayloadSerializer
 *
 * Produces deterministic JSON payloads for Chronicle entries.
 *
 * Canonical serialization ensures that identical payloads always
 * produce identical JSON representations, which is critical for:
 *
 * - payload hashing
 * - chain hashing
 * - signed exports
 * - dataset verification
 *
 * The serializer recursively sorts keys and normalizes values.
 */
class CanonicalPayloadSerializer
{
    /**
     * Serialize a payload into canonical JSON.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws JsonException
     */
    public function serialize(array $payload): string
    {
        $normalized = $this->normalize($payload);

        return json_encode(
            $normalized,
            JSON_UNESCAPED_SLASHES |
            JSON_UNESCAPED_UNICODE |
            JSON_THROW_ON_ERROR
        );
    }

    /**
     * Normalize payload data recursively.
     *
     * Ensures deterministic ordering and consistent value types.
     */
    protected function normalize(mixed $value): mixed
    {
        if (is_array($value)) {
            if ($this->isAssoc($value)) {
                ksort($value);

                foreach ($value as $key => $item) {
                    $value[$key] = $this->normalize($item);
                }

                return $value;
            }

            return array_map(fn ($item) => $this->normalize($item), $value);
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('c');
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return $value;
        }

        if ($value === null) {
            return null;
        }

        /** @var string $returnValue */
        $returnValue = $value;

        return $returnValue;
    }

    /**
     * Determine if an array is associative.
     *
     * @param  mixed[]  $array
     */
    protected function isAssoc(array $array): bool
    {
        return array_keys($array) !== range(0, count($array) - 1);
    }
}
