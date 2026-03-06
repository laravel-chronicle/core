<?php

namespace Chronicle\Facades;

use Chronicle\Entry\EntryBuilder;
use Illuminate\Support\Facades\Facade;

/**
 * Class Chronicle
 *
 * Laravel facade providing convenient access to the
 * Chronicle audit logging service.
 *
 * Example usage:
 *
 * Chronicle::entry()
 *      ->actor($user)
 *      ->action('invoice.sent')
 *      ->subject($invoice)
 *      ->record()
 *
 * The facade resolves the underlying ChronicleManager
 * from Laravel's service container.
 *
 * @method static EntryBuilder record()
 * @method static void extendDriver(string $name, callable $factory)
 */
class Chronicle extends Facade
{
    /**
     * Get the service container binding key.
     *
     * This key must match the binding registered
     * in the ChronicleServiceProvider.
     */
    protected static function getFacadeAccessor(): string
    {
        return 'chronicle';
    }
}
