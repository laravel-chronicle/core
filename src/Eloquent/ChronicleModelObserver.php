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
     * Additional fields excluded from the updated diff.
     * Override in subclasses to add model-specific ignored fields.
     *
     * @var list<string>
     */
    protected array $ignoredFields = [];

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

        if (! ModelDiffBuilder::hasRelevantChanges($model)) {
            return;
        }

        $diff = ModelDiffBuilder::build($model, $this->ignoredFields($model));

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
    protected function resolveActor(Model $model): Model|string
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
        return array_merge(
            [$model::CREATED_AT ?? 'created_at', $model::UPDATED_AT ?? 'updated_at'],
            $this->ignoredFields,
        );
    }
}
