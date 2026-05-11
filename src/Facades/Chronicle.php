<?php

namespace Chronicle\Facades;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\LedgerReader;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Query\LedgerQuery;
use Chronicle\Testing\ChronicleAssertions;
use Chronicle\Transaction\ChronicleTransaction;
use Illuminate\Support\Facades\Facade;

/**
 * Class Chronicle
 *
 * Laravel facade providing convenient access to the
 * Chronicle audit logging service.
 *
 * Example usage:
 *
 * Chronicle::record()
 *      ->actor($user)
 *      ->action('invoice.sent')
 *      ->subject($invoice)
 *      ->commit()
 *
 * The facade resolves the underlying ChronicleManager
 * from Laravel's service container.
 *
 * @method static EntryBuilder record()
 * @method static void extendDriver(string $name, callable $factory)
 * @method static void extendEntry(EntryExtension|string $extension)
 * @method static ChronicleTransaction|mixed transaction(?callable $callback = null)
 * @method static LedgerReader reader()
 * @method static LedgerQuery query()
 * @method static ChronicleTransaction|null currentTransaction()
 * @method static string|null currentCorrelation()
 * @method static StorageDriver driver(string $name)
 * @method static ChronicleAssertions fake()
 * @method static void observe(string $mode, ?string $observer = null)
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
