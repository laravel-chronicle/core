<?php

namespace Chronicle\Eloquent;

use Chronicle\Facades\Chronicle;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Base Eloquent observer that records Chronicle audit entries.
 *
 * Use when you cannot add HasChronicle to the model directly — for example,
 * models from third-party packages.
 *
 * Register in a ServiceProvider:
 *   Chronicle::observe(Invoice::class);
 *   Chronicle::observe(Invoice::class, InvoiceObserver::class);
 *   Invoice::observe(new InvoiceObserver);
 *
 * Extend and override any protected method to customize behavior.
 */
class ChronicleModelObserver
{
    /**
     * @throws Throwable
     */
    public function created(Model $model): void
    {
        if (! $this->shouldRecord($model, 'created')) {
            return;
        }

        Chronicle::record()
            ->actor($this->resolveActor($model))
            ->action($this->actionPrefix($model).'.created')
            ->subject($model)
            ->commit();
    }

    /**
     * @throws Throwable
     */
    public function updated(Model $model): void
    {
        if (! $this->shouldRecord($model, 'updated')) {
            return;
        }

        $dirty = array_diff_key($model->getDirty(), array_flip(['created_at', 'updated_at']));

        if (empty($dirty)) {
            return;
        }

        $ignored = $this->ignoredFields($model);
        $diff = [];

        foreach ($dirty as $field => $newValue) {
            if (in_array($field, $ignored, true)) {
                continue;
            }
            $diff[$field] = [
                'old' => $model->getOriginal($field),
                'new' => $newValue,
            ];
        }

        $builder = Chronicle::record()
            ->actor($this->resolveActor($model))
            ->action($this->actionPrefix($model).'.updated')
            ->subject($model);

        if (! empty($diff)) {
            $builder->diff($diff);
        }

        $builder->commit();
    }

    /**
     * @throws Throwable
     */
    public function deleted(Model $model): void
    {
        if (! $this->shouldRecord($model, 'deleted')) {
            return;
        }

        Chronicle::record()
            ->actor($this->resolveActor($model))
            ->action($this->actionPrefix($model).'.deleted')
            ->subject($model)
            ->commit();
    }

    /**
     * Whether to record the given lifecycle event for this model.
     */
    protected function shouldRecord(Model $model, string $event): bool
    {
        return in_array($event, $this->recordedEvents(), true);
    }

    /**
     * Events this observer records. Override to restrict.
     *
     * @return list<string>
     */
    protected function recordedEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }

    /**
     * Resolve the actor. Defaults to the authenticated user, or 'system'.
     */
    protected function resolveActor(Model $model): mixed
    {
        return Auth::user() ?? 'system';
    }

    /**
     * Action prefix for this model's entries (e.g. "invoice").
     * Defaults to snake_case of the model's base class name.
     */
    protected function actionPrefix(Model $model): string
    {
        return Str::snake(class_basename($model));
    }

    /**
     * Fields excluded from the updated diff.
     *
     * @return list<string>
     */
    protected function ignoredFields(Model $model): array
    {
        return ['created_at', 'updated_at'];
    }
}
