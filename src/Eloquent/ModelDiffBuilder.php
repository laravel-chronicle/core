<?php

declare(strict_types=1);

namespace Chronicle\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class ModelDiffBuilder
{
    /**
     * Returns true if the model has dirty fields beyond auto-managed timestamp columns.
     */
    public static function hasRelevantChanges(Model $model): bool
    {
        $timestampKeys = array_filter([
            $model::CREATED_AT ?? 'created_at',
            $model::UPDATED_AT ?? 'updated_at',
        ]);

        return ! empty(array_diff_key($model->getDirty(), array_flip($timestampKeys)));
    }

    /**
     * Build an old/new diff for all dirty fields, excluding ignored ones.
     * Hidden attributes (and any in $redact) have their values replaced with
     * "[redacted]" so the fact of the change is audited without leaking the value.
     *
     * @param  list<string>  $ignoreFields
     * @param  list<string>  $redact
     * @return array<string, array{old: mixed, new: mixed}>
     */
    public static function build(Model $model, array $ignoreFields, array $redact = []): array
    {
        $timestampKeys = array_filter([
            $model::CREATED_AT ?? 'created_at',
            $model::UPDATED_AT ?? 'updated_at',
        ]);

        $redactKeys = array_merge($model->getHidden(), $redact);

        $dirty = array_diff_key($model->getDirty(), array_flip($ignoreFields));
        $diff = [];

        foreach ($dirty as $field => $newValue) {
            if (in_array($field, $timestampKeys, true)) {
                continue;
            }

            if (in_array($field, $redactKeys, true)) {
                $diff[$field] = ['old' => '[redacted]', 'new' => '[redacted]'];

                continue;
            }

            $diff[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        return $diff;
    }
}
