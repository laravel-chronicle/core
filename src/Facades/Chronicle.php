<?php

declare(strict_types=1);

namespace Chronicle\Facades;

use Chronicle\Contracts\EntryExtension;
use Chronicle\Contracts\LedgerReader;
use Chronicle\Contracts\StorageDriver;
use Chronicle\Entry\Entry;
use Chronicle\Entry\EntryBuilder;
use Chronicle\Query\LedgerQuery;
use Chronicle\Support\ResolvedReference;
use Chronicle\Testing\ChronicleAssertions;
use Chronicle\Transaction\ChronicleTransaction;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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
 * @method static ResolvedReference resolveReference(string $type, string $id)
 * @method static string referenceLabel(string $type, string $id, bool $hydrate = false)
 * @method static Model|null referenceModel(string $type, string $id)
 * @method static class-string<Entry> entryModel()
 * @method static Builder<Entry> newEntryQuery()
 * @method static ChronicleTransaction|null currentTransaction()
 * @method static string|null currentCorrelation()
 * @method static StorageDriver driver(string $name)
 * @method static ChronicleAssertions fake()
 * @method static void observe(string $mode, ?string $observer = null)
 */
final class Chronicle extends Facade
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
