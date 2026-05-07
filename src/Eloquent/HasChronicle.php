<?php

namespace Chronicle\Eloquent;

use Chronicle\Facades\Chronicle;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Throwable;

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

                // Skip if only auto-managed timestamp columns changed (e.g. touch()).
                $dirty = array_diff_key($model->getDirty(), array_flip(['created_at', 'updated_at']));

                if (empty($dirty)) {
                    return;
                }

                $ignored = $model->chronicleIgnoredFields();

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
                    ->actor($model->chronicleActor())
                    ->action($model->chronicleActionPrefix().'.updated')
                    ->subject($model);

                if (! empty($diff)) {
                    $builder->diff($diff);
                }

                $builder->commit();
            });
    }

    protected function chronicleActor(): Authenticatable|string
    {
        return Auth::user() ?? 'system';
    }

    protected function chronicleActionPrefix(): string
    {
        return Str::snake(class_basename(static::class));
    }

    protected function shouldChronicleEvent(string $event): bool
    {
        $events = property_exists($this, 'chronicleEvents')
            ? $this->chronicleEvents
            : ['created', 'updated', 'deleted'];

        return in_array($event, $events, true);
    }

    /**
     * @return array<int, string>
     */
    protected function chronicleIgnoredFields(): array
    {
        $extra = property_exists($this, 'chronicleIgnore')
            ? $this->chronicleIgnore
            : [];

        return array_merge(['created_at', 'updated_at'], $extra);
    }
}
