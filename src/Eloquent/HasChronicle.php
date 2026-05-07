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
}
