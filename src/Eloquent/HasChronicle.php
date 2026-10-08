<?php

declare(strict_types=1);

namespace Chronicle\Eloquent;

use Chronicle\Facades\Chronicle;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

/**
 * Trait that gives an Eloquent model automatic Chronicle audit logging on create, update, and delete.
 */
trait HasChronicle
{
    public static function bootHasChronicle(): void
    {
        static::created(
            /** @throws Throwable */
            function (self $model): void {
                if (! $model->shouldChronicleEvent('created')) {
                    return;
                }

                Chronicle::record()
                    ->actor($model->chronicleActor())
                    ->action($model->chronicleActionPrefix().'.created')
                    ->subject($model)
                    ->commit();
            });

        static::updated(
            /** @throws Throwable */
            function (self $model): void {
                if (! $model->shouldChronicleEvent('updated')) {
                    return;
                }

                if (! ModelDiffBuilder::hasRelevantChanges($model)) {
                    return;
                }

                $diff = ModelDiffBuilder::build($model, $model->chronicleIgnoredFields(), $model->chronicleRedact());

                $builder = Chronicle::record()
                    ->actor($model->chronicleActor())
                    ->action($model->chronicleActionPrefix().'.updated')
                    ->subject($model);

                if (! empty($diff)) {
                    $builder->diff($diff);
                }

                $builder->commit();
            }
        );

        static::deleted(
            /** @throws Throwable */
            function (self $model): void {
                if (! $model->shouldChronicleEvent('deleted')) {
                    return;
                }

                Chronicle::record()
                    ->actor($model->chronicleActor())
                    ->action($model->chronicleActionPrefix().'.deleted')
                    ->subject($model)
                    ->commit();
            });
    }

    protected function chronicleActor(): mixed
    {
        return Auth::user() ?? 'system';
    }

    protected function chronicleActionPrefix(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function shouldChronicleEvent(string $event): bool
    {
        return in_array($event, $this->chronicleEvents(), true);
    }

    /**
     * @return list<string>
     */
    protected function chronicleIgnoredFields(): array
    {
        return array_merge(
            [static::CREATED_AT ?? 'created_at', static::UPDATED_AT ?? 'updated_at'],
            $this->chronicleIgnore(),
        );
    }

    /**
     * Override to define ignored fields
     *
     * @return list<string>
     */
    protected function chronicleIgnore(): array
    {
        return [];
    }

    /**
     * Override to define redacted fields
     *
     * @return list<string>
     */
    protected function chronicleRedact(): array
    {
        return [];
    }

    /**
     * Override to define triggering model events
     *
     * @return list<string>
     */
    protected function chronicleEvents(): array
    {
        return ['created', 'updated', 'deleted'];
    }
}
